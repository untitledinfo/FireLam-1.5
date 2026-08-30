#!/usr/bin/env python3
"""Merge a trained LoRA adapter into the base model, producing standalone weights
ready for GGUF conversion / Ollama packaging.

Usage:
    python src/merge_lora.py --base_model Qwen/Qwen2.5-Coder-7B-Instruct \
        --adapter outputs/firelam-1.5-lora --out outputs/firelam-1.5-merged
"""
import argparse

import torch
from peft import PeftModel
from transformers import AutoModelForCausalLM, AutoTokenizer


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--base_model", required=True)
    parser.add_argument("--adapter", required=True, help="Path to trained LoRA adapter dir")
    parser.add_argument("--out", required=True, help="Output dir for merged model")
    args = parser.parse_args()

    print(f"Loading base model: {args.base_model}")
    base = AutoModelForCausalLM.from_pretrained(
        args.base_model, torch_dtype=torch.bfloat16, device_map="auto"
    )
    tokenizer = AutoTokenizer.from_pretrained(args.base_model)

    print(f"Applying LoRA adapter from: {args.adapter}")
    model = PeftModel.from_pretrained(base, args.adapter)

    print("Merging adapter weights into base model...")
    model = model.merge_and_unload()

    print(f"Saving merged model to: {args.out}")
    model.save_pretrained(args.out, safe_serialization=True)
    tokenizer.save_pretrained(args.out)

    print("Done. Next: convert to GGUF with scripts/export_model.sh")


if __name__ == "__main__":
    main()
