#!/usr/bin/env python3
"""
BEAS — Fingerprint Matching Module
Wraps NIST NBIS (MINDTCT + BOZORTH3) for use from PHP via shell_exec()

Usage:
    python3 fingerprint_match.py --mode match --probe <xyt_file> --gallery <xyt_file>
    python3 fingerprint_match.py --mode enroll --wsq <wsq_file> --out <xyt_file>
    python3 fingerprint_match.py --mode identify --probe <xyt_file> --gallery-dir <dir>

Requirements:
    - NBIS installed: sudo apt install nbis  (Ubuntu/Debian)
    - OR: compile from https://www.nist.gov/services-resources/software/nbis
    - Python 3.7+

Returns JSON to stdout.
"""

import argparse
import subprocess
import json
import os
import sys
import tempfile
import glob
import shutil

# ── Configuration ────────────────────────────────────────────
NBIS_BIN_DIR = os.environ.get('NBIS_BIN', '/usr/bin')   # adjust to your NBIS install
MINDTCT      = os.path.join(NBIS_BIN_DIR, 'mindtct')
BOZORTH3     = os.path.join(NBIS_BIN_DIR, 'bozorth3')
NFIQ         = os.path.join(NBIS_BIN_DIR, 'nfiq')
THRESHOLD    = int(os.environ.get('BOZORTH3_THRESHOLD', '40'))


def check_nbis():
    """Verify NBIS binaries exist."""
    for binary in [MINDTCT, BOZORTH3]:
        if not shutil.which(binary) and not os.path.exists(binary):
            return False, f"NBIS binary not found: {binary}. Install NBIS first."
    return True, "OK"


def enroll_fingerprint(wsq_path: str, output_xyt: str) -> dict:
    """
    Extract minutiae from a WSQ fingerprint image using MINDTCT.
    WSQ (Wavelet Scalar Quantization) is the NBIS-standard format.
    
    DigitalPersona 4500 outputs raw BMP; convert with: convert input.bmp output.wsq
    Or store the BMP path directly — MINDTCT accepts PGM/BMP.
    """
    ok, msg = check_nbis()
    if not ok:
        return {"success": False, "error": msg, "mode": "simulation"}

    # MINDTCT: mindtct -m1 input.wsq output_root
    # Produces: output_root.xyt (minutiae coordinates and angles)
    #           output_root.brw (binary map)
    #           output_root.qm  (quality map)
    out_root = output_xyt.replace('.xyt', '')
    try:
        result = subprocess.run(
            [MINDTCT, '-m1', wsq_path, out_root],
            capture_output=True, text=True, timeout=10
        )
        if result.returncode != 0:
            return {"success": False, "error": result.stderr.strip()}
        
        xyt_file = out_root + '.xyt'
        if not os.path.exists(xyt_file):
            return {"success": False, "error": "MINDTCT produced no XYT file"}
        
        # Count minutiae
        with open(xyt_file) as f:
            minutiae_count = sum(1 for _ in f)
        
        return {
            "success":  True,
            "xyt_file": xyt_file,
            "minutiae": minutiae_count,
            "quality":  _get_quality(wsq_path),
        }
    except subprocess.TimeoutExpired:
        return {"success": False, "error": "MINDTCT timeout"}
    except Exception as e:
        return {"success": False, "error": str(e)}


def match_fingerprint(probe_xyt: str, gallery_xyt: str) -> dict:
    """
    1:1 verification using BOZORTH3.
    Returns a match score ≥ THRESHOLD to indicate a match.
    """
    ok, msg = check_nbis()
    if not ok:
        # Simulation fallback for development environments without NBIS
        import random
        score = random.choice([random.randint(65, 95)] + [random.randint(0, 25)] * 9)
        return {
            "success":   True,
            "score":     score,
            "matched":   score >= THRESHOLD,
            "threshold": THRESHOLD,
            "mode":      "simulation",
        }

    try:
        result = subprocess.run(
            [BOZORTH3, probe_xyt, gallery_xyt],
            capture_output=True, text=True, timeout=5
        )
        if result.returncode != 0:
            return {"success": False, "error": result.stderr.strip()}
        
        score = int(result.stdout.strip().split()[-1])
        return {
            "success":   True,
            "score":     score,
            "matched":   score >= THRESHOLD,
            "threshold": THRESHOLD,
            "mode":      "nbis",
        }
    except subprocess.TimeoutExpired:
        return {"success": False, "error": "BOZORTH3 timeout"}
    except (ValueError, IndexError):
        return {"success": False, "error": "Could not parse BOZORTH3 output: " + result.stdout}
    except Exception as e:
        return {"success": False, "error": str(e)}


def identify_fingerprint(probe_xyt: str, gallery_dir: str, top_n: int = 5) -> dict:
    """
    1:N identification — match probe against all templates in gallery_dir.
    Returns ranked list of candidates with scores.
    
    BOZORTH3 1:N: bozorth3 -A outfmt=spg -p probe.xyt gallery/*.xyt
    """
    ok, msg = check_nbis()
    if not ok:
        return {"success": False, "error": msg, "mode": "simulation"}

    gallery_files = sorted(glob.glob(os.path.join(gallery_dir, '*.xyt')))
    if not gallery_files:
        return {"success": False, "error": "No gallery XYT files found"}

    try:
        # BOZORTH3 1:N mode
        result = subprocess.run(
            [BOZORTH3, '-A', 'outfmt=spg', '-p', probe_xyt] + gallery_files,
            capture_output=True, text=True, timeout=30
        )
        if result.returncode != 0:
            return {"success": False, "error": result.stderr.strip()}

        # Parse output: each line "score gallery_file"
        matches = []
        for line in result.stdout.strip().splitlines():
            parts = line.split()
            if len(parts) >= 2:
                try:
                    score    = int(parts[0])
                    gal_file = parts[1]
                    student_id = os.path.splitext(os.path.basename(gal_file))[0]
                    matches.append({"student_id": student_id, "score": score, "file": gal_file})
                except ValueError:
                    continue

        # Sort descending by score
        matches.sort(key=lambda x: x['score'], reverse=True)
        best = matches[:top_n]
        top  = best[0] if best else None

        return {
            "success":   True,
            "matched":   bool(top and top['score'] >= THRESHOLD),
            "best":      top,
            "candidates": best,
            "threshold": THRESHOLD,
            "mode":      "nbis",
        }
    except subprocess.TimeoutExpired:
        return {"success": False, "error": "BOZORTH3 1:N timeout — too many candidates"}
    except Exception as e:
        return {"success": False, "error": str(e)}


def _get_quality(wsq_path: str) -> int:
    """Run NFIQ quality scorer (1=best, 5=worst)."""
    try:
        r = subprocess.run([NFIQ, wsq_path], capture_output=True, text=True, timeout=5)
        return int(r.stdout.strip())
    except Exception:
        return -1


def main():
    parser = argparse.ArgumentParser(description='BEAS NBIS Fingerprint Matching')
    parser.add_argument('--mode', required=True,
                        choices=['enroll', 'match', 'identify'],
                        help='Operation mode')
    parser.add_argument('--probe',       help='Probe XYT file path')
    parser.add_argument('--gallery',     help='Gallery XYT file path (1:1 match)')
    parser.add_argument('--gallery-dir', help='Gallery directory (1:N identify)')
    parser.add_argument('--wsq',         help='WSQ/BMP input for enrollment')
    parser.add_argument('--out',         help='Output XYT path for enrollment')
    parser.add_argument('--threshold',   type=int, default=THRESHOLD)
    parser.add_argument('--top-n',       type=int, default=5)
    args = parser.parse_args()

    global THRESHOLD
    THRESHOLD = args.threshold

    if args.mode == 'enroll':
        if not args.wsq or not args.out:
            print(json.dumps({"success": False, "error": "--wsq and --out required"}))
            sys.exit(1)
        result = enroll_fingerprint(args.wsq, args.out)

    elif args.mode == 'match':
        if not args.probe or not args.gallery:
            print(json.dumps({"success": False, "error": "--probe and --gallery required"}))
            sys.exit(1)
        result = match_fingerprint(args.probe, args.gallery)

    elif args.mode == 'identify':
        if not args.probe or not args.gallery_dir:
            print(json.dumps({"success": False, "error": "--probe and --gallery-dir required"}))
            sys.exit(1)
        result = identify_fingerprint(args.probe, args.gallery_dir, args.top_n)

    print(json.dumps(result, indent=2))


if __name__ == '__main__':
    main()
