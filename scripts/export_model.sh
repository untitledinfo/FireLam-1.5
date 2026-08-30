#!/usr/bin/env bash
# Convert a merged Hugging Face model to GGUF and quantize it for Ollama.
#
# Usage: bash scripts/export_model.sh <merged_model_dir> <output_name> [quant_type]
#   quant_type defaults to Q4_K_M (good quality/size tradeoff for local use)
set -euo pipefail

MERGED_DIR="${1:?Usage: export_model.sh <merged_model_dir> <output_name> [quant_type]}"
OUT_NAME="${2:?Usage: export_model.sh <merged_model_dir> <output_name> [quant_type]}"
QUANT="${3:-Q4_K_M}"

LLAMA_CPP_DIR="third_party/llama.cpp"

if [ ! -d "$LLAMA_CPP_DIR" ]; then
  echo "Cloning llama.cpp (needed for GGUF conversion)..."
  mkdir -p third_party
  git clone --depth 1 https://github.com/ggerganov/llama.cpp "$LLAMA_CPP_DIR"
  pip install -r "$LLAMA_CPP_DIR/requirements.txt"
fi

echo "Converting $MERGED_DIR to GGUF (f16)..."
python3 "$LLAMA_CPP_DIR/convert_hf_to_gguf.py" "$MERGED_DIR" \
  --outfile "ollama/${OUT_NAME}-f16.gguf" \
  --outtype f16

echo "Quantizing to $QUANT..."
# Build the quantize binary once if it doesn't exist yet
if [ ! -f "$LLAMA_CPP_DIR/build/bin/llama-quantize" ]; then
  cmake -S "$LLAMA_CPP_DIR" -B "$LLAMA_CPP_DIR/build"
  cmake --build "$LLAMA_CPP_DIR/build" --target llama-quantize -j
fi

"$LLAMA_CPP_DIR/build/bin/llama-quantize" \
  "ollama/${OUT_NAME}-f16.gguf" \
  "ollama/${OUT_NAME}.gguf" \
  "$QUANT"

echo ""
echo "Done: ollama/${OUT_NAME}.gguf"
echo "Update ollama/Modelfile's FROM line to point at this file, then run:"
echo "  ollama create ${OUT_NAME} -f ollama/Modelfile"
