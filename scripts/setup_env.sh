#!/usr/bin/env bash
set -euo pipefail

echo "Setting up FireLam 1.5 training environment..."

python3 -m venv .venv
source .venv/bin/activate

pip install --upgrade pip
pip install -r requirements.txt

echo ""
echo "Checking for CUDA GPU..."
python3 -c "import torch; print('CUDA available:', torch.cuda.is_available()); print('Device:', torch.cuda.get_device_name(0) if torch.cuda.is_available() else 'none')"

echo ""
echo "Environment ready. Activate with: source .venv/bin/activate"
