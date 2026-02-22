# README.md

# kasax_child : AI POST Analysis Unit (Python Engine)

This unit serves as the "intelligence core" of the creative support system *kasax_child*, leveraging advanced Natural Language Processing (NLP) and Machine Learning.

## Overview

This engine analyzes vast amounts of creative notes stored on WordPress, scoring "knowledge maturity" and performing vectorization through proprietary algorithms. It elevates a simple collection of articles into a structured, quantified "Multi-layered Intelligent Knowledge Base."

## Core Features

### 1. Global Analysis (GA)

* **Keyword Extraction via TF-IDF**: Extracts nouns from the entire site using GiNZA (spaCy).
* **Statistical Weighting**: Defines the rarity and importance of words across the site (`global_term_weights.json`), forming the foundation for scoring.

### 2. Knowledge Classification (KC)

* **Quality Prediction via Regression**: Utilizes a Ridge Regression model to predict "knowledge completeness" on a scale of 0.0 to 5.0 based on context.
* **Prefix Learning**: The AI learns the patterns of human-defined classifications (Μ, Β, γ, σ, δ) and determines how well new articles fit into these categories.

### 3. Mixed Analysis & Standardization (MX)

* **Integrated Scoring**: Combines structural data (tag density, character count) with AI predictions to calculate the final `ai_score`.
* **IQ-style Deviation**: Converts raw scores into an "IQ style" distribution (Mean: 100, Standard Deviation: 15), making the value of knowledge intuitively understandable.

### 4. Vectorization (Vectorizer)

* **Semantic Quantization**: Interfaces with local LLM endpoints (LM Studio, Ollama, etc.) to transform articles into vector embeddings.
* **Future Scalability**: Provides the foundation for extracting related articles based on "semantic similarity," which is impossible with traditional keyword searches.

## Directory Structure

* `bin/`: Batch files for execution (automating each step).
* `core/`: Main logic (steps for analysis, training, inference, and standardization).
* `utils/`: Shared utilities (text cleansing, ID resolution logic, score calculation).
* `models/`: Trained models (`.pkl`) and statistical data (`.json`, `.csv`).
* `config/`: System settings, database connection info, and scoring thresholds.

## Execution Pipeline

Knowledge base intelligence is achieved by executing the scripts in the following order:

1. **`ga_def_term_weights.py`**: Defines the global importance weights for terms across the site.
2. **`kc_step1_build_training_samples.py`**: Generates training data for the AI from existing articles.
3. **`kc_step2_train_classifier.py`**: Trains the AI regression model.
4. **`kc_step3_ai_context_scorer.py`**: Performs integrated AI + statistical scoring for all articles.
5. **`kc_step4_standardizer.py`**: Converts scores into deviation values (IQ 100 base).
6. **`mx_run_vectorizer.py`**: Executes vectorization of the articles.

## Unique Logic: Consolidation to KX Structure

Equipped with `ut_mapping_resolver.py`, the system automatically aggregates analysis results from "Detailed Articles" (children) and saves them as metadata for "Overview Articles" (parents). This allows for efficient management of knowledge with complex hierarchical structures.

---

### Communication

The author is a native Japanese speaker. While inquiries in English are welcome and handled via translation tools, communicating in Japanese will ensure a smoother and faster response.

> **Note:** This document was drafted with the assistance of AI (Gemini) to accurately reflect the internal code structure.

