#!/usr/bin/env python3
import sys, json, base64, io, os, warnings
from contextlib import redirect_stdout, redirect_stderr
from PIL import Image
import numpy as np

warnings.filterwarnings(
    'ignore',
    message=r"Specified provider 'CUDAExecutionProvider' is not in available provider names.*",
    category=UserWarning,
)
warnings.filterwarnings(
    'ignore',
    message=r'`estimate` is deprecated since version 0\.26.*',
    category=FutureWarning,
)

# Prefer the vendored InsightFace package from this repository when present.
REPO_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
LOCAL_PYTHON_PACKAGE = os.path.join(REPO_ROOT, 'insightface', 'python-package')
if os.path.isdir(LOCAL_PYTHON_PACKAGE) and LOCAL_PYTHON_PACKAGE not in sys.path:
    sys.path.insert(0, LOCAL_PYTHON_PACKAGE)

# Try to import insightface and prepare a FaceAnalysis app
try:
    from insightface.app import FaceAnalysis
except Exception as e:
    sys.stdout.write(json.dumps({'error': 'insightface import failed', 'detail': str(e)}) + '\n')
    sys.exit(1)


MODEL_ROOT_CANDIDATES = [
    os.environ.get('INSIGHTFACE_MODEL_ROOT', '').strip(),
    os.path.join(REPO_ROOT, 'insightface'),
    os.path.join(REPO_ROOT, 'insightface', 'python-package'),
]


def resolve_model_root():
    home_root = os.environ.get('USERPROFILE') or os.environ.get('HOME') or ''
    if home_root:
        MODEL_ROOT_CANDIDATES.append(os.path.join(home_root, '.insightface'))

    for root in MODEL_ROOT_CANDIDATES:
        if not root:
            continue
        models_dir = os.path.join(root, 'models')
        if os.path.isdir(models_dir):
            return os.path.abspath(root)
    return os.path.abspath(os.path.join(REPO_ROOT, 'insightface'))


MODEL_ROOT = resolve_model_root()


def load_app():
    # Only use locally cached models. Do not block the request while trying to download.
    preferred_names = ['buffalo_l', 'buffalo_s', 'buffalo_sc', 'buffalo_mobile', 'buffalo']
    for name in preferred_names:
        if name:
            model_dir = os.path.join(MODEL_ROOT, 'models', name)
            if not os.path.isdir(model_dir):
                continue
            if not any(entry.lower().endswith('.onnx') for entry in os.listdir(model_dir)):
                continue
        for ctx in (0, -1):
            try:
                with open(os.devnull, 'w') as devnull, redirect_stdout(devnull), redirect_stderr(devnull):
                    app = FaceAnalysis(
                        name=name,
                        root=MODEL_ROOT,
                        allowed_modules=['detection', 'recognition'],
                        providers=['CPUExecutionProvider'],
                    )
                    app.prepare(ctx_id=ctx, det_size=(640, 640))
                # attach chosen model name for reporting
                app._insightface_model_name = name
                return app
            except Exception:
                continue
    return None


def image_from_b64(b64str):
    try:
        imgdata = base64.b64decode(b64str)
        img = Image.open(io.BytesIO(imgdata)).convert('RGB')
        return img
    except Exception as e:
        raise RuntimeError('Invalid base64 image: ' + str(e))


def main():
    # If a file path argument is provided, load image from file (avoids large stdin transfers)
    img = None
    if len(sys.argv) > 1 and os.path.exists(sys.argv[1]):
        try:
            img = Image.open(sys.argv[1]).convert('RGB')
        except Exception as e:
            sys.stdout.write(json.dumps({'error': 'bad_image_file', 'detail': str(e)}) + '\n')
            sys.exit(1)
    else:
        try:
            payload = json.load(sys.stdin)
        except Exception as e:
            sys.stdout.write(json.dumps({'error': 'invalid_json', 'detail': str(e)}) + '\n')
            sys.exit(1)

        b64 = payload.get('image_b64') or payload.get('probeImage') or ''
        if not b64:
            sys.stdout.write(json.dumps({'error': 'no_image_provided'}) + '\n')
            sys.exit(1)

        try:
            img = image_from_b64(b64)
        except Exception as e:
            sys.stdout.write(json.dumps({'error': 'bad_image', 'detail': str(e)}) + '\n')
            sys.exit(1)

    app = load_app()
    if not app:
        sys.stdout.write(json.dumps({
            'error': 'could_not_load_insightface_model',
            'detail': f'No local InsightFace model cache found under {os.path.join(MODEL_ROOT, "models")}'
        }) + '\n')
        sys.exit(1)

    try:
        arr = np.asarray(img)
        faces = app.get(arr)
        if not faces:
            sys.stdout.write(json.dumps({'error': 'no_face_detected'}) + '\n')
            sys.exit(1)
        face = faces[0]
        emb = None
        if hasattr(face, 'normed_embedding') and face.normed_embedding is not None:
            emb = face.normed_embedding.tolist()
        elif hasattr(face, 'embedding') and face.embedding is not None:
            # normalize
            v = np.array(face.embedding, dtype=float)
            n = np.linalg.norm(v)
            if n > 0:
                v = (v / n).tolist()
            emb = [float(x) for x in v]
        else:
            sys.stdout.write(json.dumps({'error': 'no_embedding_found'}) + '\n')
            sys.exit(1)

        model_name = getattr(app, '_insightface_model_name', 'unknown')
        sys.stdout.write(json.dumps({'embedding': emb, 'model': model_name}) + '\n')
    except Exception as e:
        sys.stdout.write(json.dumps({'error': 'processing_failed', 'detail': str(e)}) + '\n')
        sys.exit(1)


if __name__ == '__main__':
    main()
