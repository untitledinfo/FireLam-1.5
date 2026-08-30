FROM nvidia/cuda:12.1.1-runtime-ubuntu22.04

RUN apt-get update && apt-get install -y \
    python3.11 python3-pip git cmake build-essential \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /workspace
COPY requirements.txt .
RUN pip3 install --no-cache-dir -r requirements.txt

COPY . .

# Default: drop into a shell. Override CMD to run train.py / inference.py directly.
CMD ["/bin/bash"]
