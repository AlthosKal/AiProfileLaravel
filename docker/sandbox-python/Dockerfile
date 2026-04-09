FROM python:3.12-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
        fontconfig \
        fonts-liberation \
        fonts-crosextra-carlito \
        fonts-dejavu \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN pip install --no-cache-dir \
    openpyxl==3.1.5 \
    reportlab==4.2.5 \
    matplotlib==3.9.4 \
    pandas==2.2.3 \
    Pillow==10.4.0

RUN useradd --no-create-home --shell /bin/false sandbox

WORKDIR /workspace

USER sandbox
