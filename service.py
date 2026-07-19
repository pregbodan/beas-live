import json
import os
import sys
import threading
from typing import Any, Dict, List, Optional

import numpy as np
from fastapi import FastAPI, HTTPException
from fastapi.responses import JSONResponse
from insightface.app import FaceAnalysis
from PIL import Image
import base64
import io


APP = FastAPI(title="BEAS InsightFace Service", version="1.0.0")

MODEL_ROOT = os.environ.get("INSIGHTFACE_MODEL_ROOT", "/models").strip() or "/models"
MODEL_NAME = os.environ.get("INSIGHTFACE_MODEL_NAME", "buffalo_s").strip() or "buffalo_s"
CACHE_FILE = os.environ.get("INSIGHTFACE_CACHE_FILE", "/data/embeddings.json").strip() or "/data/embeddings.json"
PORT = int(os.environ.get("PORT", "8080"))

_model_lock = threading.Lock()
_cache_lock = threading.Lock()
_model: Optional[FaceAnalysis] = None
_model_error: Optional[str] = None
_embedding_cache: Dict[str, List[float]] = {}


def _ensure_dirs() -> None:
    os.makedirs(MODEL_ROOT, exist_ok=True)
    cache_dir = os.path.dirname(CACHE_FILE)
    if cache_dir:
        os.makedirs(cache_dir, exist_ok=True)


def _load_cache() -> None:
    global _embedding_cache
    if not os.path.isfile(CACHE_FILE):
        _embedding_cache = {}
        return
    try:
        with open(CACHE_FILE, "r", encoding="utf-8") as fh:
            data = json.load(fh)
        if isinstance(data, dict):
            _embedding_cache = {
                str(k): [float(v) for v in (values or [])]
                for k, values in data.items()
                if isinstance(values, list)
            }
        else:
            _embedding_cache = {}
    except Exception:
        _embedding_cache = {}


def _save_cache() -> None:
    with open(CACHE_FILE, "w", encoding="utf-8") as fh:
        json.dump(_embedding_cache, fh)


def _image_from_b64(b64str: str) -> Image.Image:
    if not b64str:
        raise ValueError("No image provided")
    if "," in b64str and b64str.startswith("data:"):
        b64str = b64str.split(",", 1)[1]
    try:
        imgdata = base64.b64decode(b64str)
        return Image.open(io.BytesIO(imgdata)).convert("RGB")
    except Exception as exc:
        raise ValueError(f"Invalid base64 image: {exc}") from exc


def _load_model() -> Optional[FaceAnalysis]:
    global _model, _model_error
    with _model_lock:
        if _model is not None:
            return _model

        last_error: Optional[str] = None
        for name in (MODEL_NAME, "buffalo_s", "buffalo_sc", "buffalo_l", "buffalo_mobile", "buffalo"):
            try:
                app = FaceAnalysis(
                    name=name,
                    root=MODEL_ROOT,
                    allowed_modules=["detection", "recognition"],
                    providers=["CPUExecutionProvider"],
                )
                app.prepare(ctx_id=0, det_size=(640, 640))
                app._insightface_model_name = name  # type: ignore[attr-defined]
                _model = app
                _model_error = None
                return _model
            except Exception as exc:
                last_error = str(exc)

        _model_error = last_error or "Could not load InsightFace model"
        return None


def _normalize_embedding(face: Any) -> Optional[List[float]]:
    if hasattr(face, "normed_embedding") and face.normed_embedding is not None:
        return [float(v) for v in face.normed_embedding.tolist()]
    if hasattr(face, "embedding") and face.embedding is not None:
        vec = np.array(face.embedding, dtype=float)
        norm = np.linalg.norm(vec)
        if norm > 0:
            vec = vec / norm
        return [float(v) for v in vec.tolist()]
    return None


@APP.on_event("startup")
def _startup() -> None:
    _ensure_dirs()
    _load_cache()
    _load_model()


@APP.get("/health")
def health() -> JSONResponse:
    model = _model or _load_model()
    if model is None:
        return JSONResponse(
            {
                "ok": False,
                "model": None,
                "modelRoot": MODEL_ROOT,
                "embeddingCacheSize": len(_embedding_cache),
                "enrolledCacheSize": len(_embedding_cache),
                "error": "model_not_ready",
                "detail": _model_error or "InsightFace model is not ready",
            },
            status_code=503,
        )

    return JSONResponse(
        {
            "ok": True,
            "model": getattr(model, "_insightface_model_name", MODEL_NAME),
            "modelRoot": MODEL_ROOT,
            "embeddingCacheSize": len(_embedding_cache),
            "enrolledCacheSize": len(_embedding_cache),
        }
    )


@APP.post("/embed")
def embed(payload: Dict[str, Any]) -> JSONResponse:
    model = _model or _load_model()
    if model is None:
        raise HTTPException(status_code=503, detail=_model_error or "InsightFace model is not ready")

    probe = payload.get("probeImage") or payload.get("image_b64") or payload.get("image") or ""
    try:
        img = _image_from_b64(str(probe))
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc

    arr = np.asarray(img)
    faces = model.get(arr)
    if not faces:
        raise HTTPException(status_code=422, detail="no_face_detected")

    embedding = _normalize_embedding(faces[0])
    if not embedding:
        raise HTTPException(status_code=422, detail="no_embedding_found")

    return JSONResponse(
        {
            "ok": True,
            "embedding": embedding,
            "model": getattr(model, "_insightface_model_name", MODEL_NAME),
        }
    )


@APP.post("/sync")
def sync(payload: Dict[str, Any]) -> JSONResponse:
    embeddings = payload.get("embeddings", [])
    if not isinstance(embeddings, list):
        raise HTTPException(status_code=400, detail="embeddings must be an array")

    updated = 0
    with _cache_lock:
        for item in embeddings:
            if not isinstance(item, dict):
                continue
            student_id = item.get("studentId")
            vector = item.get("embedding")
            if student_id is None or not isinstance(vector, list):
                continue
            try:
                _embedding_cache[str(student_id)] = [float(v) for v in vector]
                updated += 1
            except Exception:
                continue
        _save_cache()

    return JSONResponse(
        {
            "ok": True,
            "synced": updated,
            "embeddingCacheSize": len(_embedding_cache),
            "enrolledCacheSize": len(_embedding_cache),
        }
    )


if __name__ == "__main__":
    import uvicorn

    uvicorn.run("service:APP", host="0.0.0.0", port=PORT, log_level="info")
