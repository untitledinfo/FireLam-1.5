#!/usr/bin/env python3
"""FireLam 1.5 — LoRA/QLoRA fine-tuning entry point.

Usage:
    python src/train.py --config configs/training_config.yaml \
        --model_config configs/model_config.yaml --lora_config configs/lora_config.yaml
"""
import argparse

import torch
import yaml
from datasets import load_dataset
from peft import LoraConfig, get_peft_model, prepare_model_for_kbit_training
from transformers import (
    AutoModelForCausalLM,
    AutoTokenizer,
    BitsAndBytesConfig,
    TrainingArguments,
)
from trl import SFTTrainer


def load_yaml(path: str) -> dict:
    with open(path, "r") as f:
        return yaml.safe_load(f)


def build_model_and_tokenizer(model_cfg: dict, lora_cfg: dict):
    base_model = model_cfg["base_model"]

    tokenizer = AutoTokenizer.from_pretrained(
        base_model, trust_remote_code=model_cfg.get("trust_remote_code", False)
    )
    if tokenizer.pad_token is None:
        tokenizer.pad_token = tokenizer.eos_token

    quant_config = None
    if lora_cfg.get("use_qlora", True):
        quant_config = BitsAndBytesConfig(
            load_in_4bit=True,
            bnb_4bit_compute_dtype=getattr(torch, lora_cfg.get("bnb_4bit_compute_dtype", "bfloat16")),
            bnb_4bit_quant_type=lora_cfg.get("bnb_4bit_quant_type", "nf4"),
            bnb_4bit_use_double_quant=lora_cfg.get("bnb_4bit_use_double_quant", True),
        )

    model = AutoModelForCausalLM.from_pretrained(
        base_model,
        quantization_config=quant_config,
        device_map="auto",
        trust_remote_code=model_cfg.get("trust_remote_code", False),
        torch_dtype=torch.bfloat16,
    )

    if lora_cfg.get("use_qlora", True):
        model = prepare_model_for_kbit_training(model)

    peft_config = LoraConfig(
        r=lora_cfg["r"],
        lora_alpha=lora_cfg["lora_alpha"],
        lora_dropout=lora_cfg["lora_dropout"],
        bias=lora_cfg["bias"],
        task_type=lora_cfg["task_type"],
        target_modules=lora_cfg["target_modules"],
    )
    model = get_peft_model(model, peft_config)
    model.print_trainable_parameters()

    return model, tokenizer


def format_example(example: dict, tokenizer) -> dict:
    """Apply the tokenizer's chat template to a {'messages': [...]} example."""
    text = tokenizer.apply_chat_template(
        example["messages"], tokenize=False, add_generation_prompt=False
    )
    return {"text": text}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--config", default="configs/training_config.yaml")
    parser.add_argument("--model_config", default="configs/model_config.yaml")
    parser.add_argument("--lora_config", default="configs/lora_config.yaml")
    args = parser.parse_args()

    train_cfg = load_yaml(args.config)
    model_cfg = load_yaml(args.model_config)
    lora_cfg = load_yaml(args.lora_config)

    model, tokenizer = build_model_and_tokenizer(model_cfg, lora_cfg)

    dataset = load_dataset("json", data_files=train_cfg["dataset_path"], split="train")
    dataset = dataset.map(lambda ex: format_example(ex, tokenizer))

    eval_dataset = None
    if train_cfg.get("eval_dataset_path"):
        eval_dataset = load_dataset("json", data_files=train_cfg["eval_dataset_path"], split="train")
        eval_dataset = eval_dataset.map(lambda ex: format_example(ex, tokenizer))

    training_args = TrainingArguments(
        output_dir=train_cfg["output_dir"],
        num_train_epochs=train_cfg["num_train_epochs"],
        per_device_train_batch_size=train_cfg["per_device_train_batch_size"],
        gradient_accumulation_steps=train_cfg["gradient_accumulation_steps"],
        learning_rate=train_cfg["learning_rate"],
        lr_scheduler_type=train_cfg["lr_scheduler_type"],
        warmup_ratio=train_cfg["warmup_ratio"],
        weight_decay=train_cfg["weight_decay"],
        max_grad_norm=train_cfg["max_grad_norm"],
        logging_steps=train_cfg["logging_steps"],
        save_strategy=train_cfg["save_strategy"],
        save_steps=train_cfg["save_steps"],
        save_total_limit=train_cfg["save_total_limit"],
        eval_strategy=train_cfg["eval_strategy"] if eval_dataset else "no",
        eval_steps=train_cfg.get("eval_steps"),
        optim=train_cfg["optim"],
        bf16=train_cfg["bf16"],
        gradient_checkpointing=train_cfg["gradient_checkpointing"],
        seed=train_cfg["seed"],
        report_to=train_cfg["report_to"],
        run_name=train_cfg["run_name"],
    )

    trainer = SFTTrainer(
        model=model,
        args=training_args,
        train_dataset=dataset,
        eval_dataset=eval_dataset,
        dataset_text_field="text",
        max_seq_length=model_cfg["max_seq_length"],
        tokenizer=tokenizer,
    )

    trainer.train()
    trainer.save_model(train_cfg["output_dir"])
    tokenizer.save_pretrained(train_cfg["output_dir"])
    print(f"\nTraining complete. LoRA adapter saved to {train_cfg['output_dir']}")


if __name__ == "__main__":
    main()
