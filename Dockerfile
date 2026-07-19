FROM python:3.12-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    INSIGHTFACE_MODEL_ROOT=/models \
    INSIGHTFACE_MODEL_NAME=buffalo_s \
    INSIGHTFACE_CACHE_FILE=/data/embeddings.json \
    PORT=8080

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    gcc \
    g++ \
    cmake \
    git \
    libgomp1 \
    libglib2.0-0 \
    libgl1 \
    libsm6 \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt /app/requirements.txt

RUN pip install --upgrade pip
RUN pip install --no-cache-dir -r /app/requirements.txt
COPY service.py /app/service.py
COPY python /app/python

RUN mkdir -p /models /data

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
  CMD python -c "import os,urllib.request; urllib.request.urlopen('http://127.0.0.1:%s/health' % os.environ.get('PORT','8080')).read()" \
  || exit 1

CMD ["sh", "-c", "uvicorn service:APP --host 0.0.0.0 --port ${PORT:-8080}"]
