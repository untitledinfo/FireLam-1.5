#!/usr/bin/env python3
"""Simple CLI chat loop for a FireLam checkpoint (merged model, not GGUF).
For day-to-day local use, prefer `ollama run firelam-1.5` after export — this
script is mainly for quick sanity checks straight after training/merging.

Usage:
    python src/inference.py --model outputs/firelam-1.5-merged
"""
import argparse

import torch
import yaml
from transformers import AutoModelForCausalLM, AutoTokenizer

from safety_filter import check_input, check_output


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--model", required=True, help="Path to merged model dir")
    parser.add_argument("--model_config", default="configs/model_config.yaml")
    parser.add_argument("--max_new_tokens", type=int, default=512)
    parser.add_argument("--temperature", type=float, default=0.7)
    args = parser.parse_args()

    with open(args.model_config) as f:
        model_cfg = yaml.safe_load(f)

    print(f"Loading {args.model} ...")
    tokenizer = AutoTokenizer.from_pretrained(args.model)
    model = AutoModelForCausalLM.from_pretrained(
        args.model, torch_dtype=torch.bfloat16, device_map="auto"
    )
    model.eval()

    messages = [{"role": "system", "content": model_cfg.get("system_prompt", "")}]
    print("FireLam ready. Type 'exit' to quit.\n")

    while True:
        user_input = input("You: ").strip()
        if user_input.lower() in {"exit", "quit"}:
            break

        gate = check_input(user_input)
        if not gate.allowed:
            print(f"FireLam: [blocked by safety filter: {gate.reason}]\n")
            continue

        messages.append({"role": "user", "content": user_input})
        prompt = tokenizer.apply_chat_template(
            messages, tokenize=False, add_generation_prompt=True
        )
        inputs = tokenizer(prompt, return_tensors="pt").to(model.device)

        with torch.no_grad():
            output_ids = model.generate(
                **inputs,
                max_new_tokens=args.max_new_tokens,
                temperature=args.temperature,
                do_sample=args.temperature > 0,
                pad_token_id=tokenizer.eos_token_id,
            )

        reply = tokenizer.decode(
            output_ids[0][inputs["input_ids"].shape[1]:], skip_special_tokens=True
        )

        out_gate = check_output(reply)
        if not out_gate.allowed:
            reply = f"[response withheld by safety filter: {out_gate.reason}]"

        print(f"FireLam: {reply}\n")
        messages.append({"role": "assistant", "content": reply})


if __name__ == "__main__":
    main()
