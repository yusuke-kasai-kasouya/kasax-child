# kasax_child (kasax Child Theme)

**Version: 0.2026.0904**

> **Dynamic Intelligence Storage: Mapping Conceptual Hierarchies and Multi-perspective Thinking.**

## Overview

`kasax_child` is a custom child theme developed to transform WordPress from a simple blogging tool into a powerful **Creative Support Knowledge Base**.

This project is an "experimental implementation" designed to optimize thought organization and plot construction, as used daily by the author in a local environment (XAMPP).

**The system is fully optimized for use in a Japanese language environment, specifically designed to seamlessly handle complex hierarchical management using Japanese characters and double-byte symbols.**

**Note:** This theme is specifically designed for personal use and local development environments (XAMPP, etc.). Since the implementation of "original thinking logic" is prioritized over general security and broad compatibility, use on public servers is not recommended.

## Preview

| Main Display (UI) | Editing & Admin (Editor) |
| --- | --- |
| ![Logic Snapshot](images/sample1.png) | ![UI Snapshot](images/sample2.png) |

---

## Original Concept: Multi-Tree Intelligence Storage

This theme aims to multidimensionalize information through three core pillars:

### Multi-Tree Deployment of Knowledge
By using the logical path separator "≫" in titles, a single article can exist simultaneously in multiple contexts, allowing for the construction of complex thought hierarchies.

---

## Main Features & Shortcodes

### 1. Hierarchical Navigator `［raretu］`

By writing `A≫B≫C` in the post title, the theme automatically analyzes the directory structure.

* Generates a list of child hierarchies when placed in a parent post.
* The UI (colors) changes dynamically based on specific prefixes.

### 2. Visual Categorization via Post Prefixes

Define the nature of information by adding specific characters to the beginning of post titles.

| Prefix | Category | Meaning / Role |
| --- | --- | --- |
| **κ** | Planning | Strategy / Milestones |
| **λ** | SYSTEM | System Specifications |
| **Μ** | Theory | Theoretical Framework / Analysis |
| **Β/γ/σ/δ** | Shared Resources | Lessons / Sensitivity / Research / Documentation |
| **∫** | Production | Prototype / Experiment / Draft |
| **∬00** | Production | Final Production (Execution) |

### 3. Ghost Clone `［ghost id=xxx］`

Opens a "window" to a specific post. It creates a clone page that synchronizes and displays the content of the referenced post in real-time.

### 4. Inline Modal Editor

Provides a lightweight, in-place editing interface without page transitions.

* **INFO Panel**: Displays initial publication date and latest modification date.
* **Consolidator UI**: Integrated tool to aggregate multiple posts into formatted text or EPUB for AI workflows.

---

## System Configuration Files

The system is highly modularized and controlled by the following class groups:

<details>
<summary>📂 Show configuration details (admin, batch, core, database, etc.)</summary>

### 📂 admin

| Filename | Class Name | Description |
| --- | --- | --- |
| class-admin-dashboard.php | `AdminDashboard` | Visualization of system status, DB diagnostics, and virtual hierarchy management. |
| class-list_table.php | `Kx_List_Table` | Extends WP_List_Table to display and sort custom database content (wp_kx_0, etc.). |

### 📂 batch

| Filename | Class Name | Description |
| --- | --- | --- |
| class-batch-AdvancedProcessor.php | `AdvancedProcessor` | Batch processing for title/body replacement and data migration to specific tables. |

### 📂 component

| Filename | Class Name | Description |
| --- | --- | --- |
| class-editor.php | `Editor` | Modal editor with INFO panel displaying post/modified dates and Ghost integration. |
| class-KxLink.php | `KxLink` | Generates context-optimized card-style links dynamically. |
| class-post_card.php | `PostCard` | Generates card-format HTML including summaries based on hierarchical paths. |
| class-QuickInserter.php | `QuickInserter` | Component to insert new posts while inheriting parent path context and color settings. |

### 📂 core

| Filename | Class Name | Description |
| --- | --- | --- |
| class-kx-ai-bridge.php | `KxAiBridge` | Links post data with AI metadata and optimizes LLM context supply. |
| class-kx-ajax-handler.php | `AjaxHandler` | Handles Ajax requests from the frontend to various core classes. |
| class-kx-assets.php | `KxAssets` | Asset manager that scans and enqueues CSS/JS based on hierarchy. |
| class-kx-color-manager.php | `ColorManager` | Dynamic engine solving HSL CSS variables based on title patterns and context. |
| class-kx-consolidator.php | `KxConsolidator` | Orchestrates multi-level sanitization (Lv0-5) and AI model chunk splitting. |
| class-kx-content-filter.php | `ContentFilter` | Main filters via `the_content` hook for Ghost summoning and conversion. |
| class-kx-Content-Processor.php | `ContentProcessor` | Content conversion engine for Markdown parsing and custom shorthand expansion. |
| class-kx-context_manager.php | `ContextManager` | Orchestrator for hierarchy analysis and search table synchronization. |
| class-kx-director.php | `KxDirector` | Facade class managing shortcode registration and component access. |
| class-kx-dy-content-handler.php | `DyContentHandler` | Data handler for raw content and three-layer cache (raw/ana/vis). |
| class-kx-dy-handler.php | `DyDomainHandler` | Base status manager for domain monitoring and external integration. |
| class-kx-dynamicRegistry.php | `DynamicRegistry` | Data hub for in-memory management across the theme. |
| class-kx-dy-path-index-handler.php | `DyPathIndexHandler` | Path analyzer solving "≫" separators, definitions, and post/modified dates. |
| class-kx-dy-storage.php | `DyStorage` | Low-level cache storage physically holding domain-specific properties. |
| class-kx-LaravelClient.php | `LaravelClient` | API client for communicating with external Laravel applications. |
| class-kx-outline_manager.php | `OutlineManager` | Analyzes heading levels to generate hierarchical Table of Contents HTML. |
| class-kx-query.php | `KxQuery` | Multi-layered search engine combining custom DB, API, and WP_Query. |
| class-kx-save-manager.php | `SaveManager` | Ensures data integrity during post saving, including custom table syncing. |
| class-kx-short-code.php | `ShortCode` | Execution handler for shortcodes like `ghost` and `raretu`. |
| class-kx-systemConfig.php | `SystemConfig` | Config manager for constants, paths, and external JSON settings. |
| class-kx-title-parser.php | `TitleParser` | Semantic analysis engine determining "post types" based on naming conventions. |

### 📂 database

| Filename | Class Name | Description |
| --- | --- | --- |
| class-abstract-data_manager.php | `AbstractDataManager` | Base abstract class for custom table access and cache management. |
| class-DB.php | `DB` | Manages low-level DB operations, table creation, and CSV backups. |
| class-dbkx-0-post-search-mapper.php | `dbkx0_PostSearchMapper` | High-speed index mapper for title hierarchies and types in the `kx_0` table. |
| class-dbkx-1-data-manager.php | `dbkx1_DataManager` | Manages content metadata and shortcode extraction for the `kx_1` table. |
| class-dbkx-ai-metadata-mapper.php | `dbKxAiMetadataMapper` | Maintenance and retrieval for AI-related management tables. |
| class-dbkx-Hierarchy.php | `Hierarchy` | Maps hierarchical path information to the `kx_hierarchy` table. |
| class-dbkx-shared_title_manager.php | `dbkx_SharedTitleManager` | Cross-domain title manager for linking concept IDs across different prefixes. |

### 📂 launcher

| Filename | Class Name | Description |
| --- | --- | --- |
| class-kx-post-launcher.php | `KxPostLauncher` | Front-end launcher controlling the execution and density of the `kx` shortcode. |

### 📂 matrix

| Filename | Class Name | Description |
| --- | --- | --- |
| class-1orchestrator.php | `Orchestrator` | Pipeline controller for Matrix rendering and data collection. |
| class-2query.php | `Query` | Dedicated query builder for matrix/grid data extraction. |
| class-3data_collector.php | `DataCollector` | Pre-processor for building datasets required for timeline/matrix rendering. |
| class-4processor.php | `Processor` | Logic processor that formats collected data into specific matrix structures. |
| class-5renderer.php | `Renderer` | Rendering engine outputting final HTML for timeline and board views. |

### 📂 parser

| Filename | Class Name | Description |
| --- | --- | --- |
| class-kx-parsedown.php | `KxParsedown` | Custom Markdown renderer based on ParsedownExtra with LaTeX protection. |

### 📂 utils

| Filename | Class Name | Description |
| --- | --- | --- |
| class-kx-message.php | `KxMessage` | Utility for stack-based management of system errors and notifications. |
| class-kx-workstation.php | `WorkStation` | Advanced task board providing situational UI based on prefix scans. |
| class-kx-taskboard.php | `TaskBoard` | Generates task-oriented dashboards for standard contexts. |
| class-kx-template.php | `KxTemplate` | Specialized template engine for separating logic from presentation. |
| class-kx-time.php | `Time` | Time control utility for timezone management and age/date change detection. |
| class-kx-Toolbox.php | `Toolbox` | Multi-purpose utility for debug dumps, EPUB conversion, and file saving. |
| class-kx-UI.php | `KxUI` | UI utility for generating common components like buttons and labels. |
| class-kx-wp-tweak.php | `WpTweak` | Maintenance utility for fine-tuning default WordPress behaviors. |

### 📂 visual

| Filename | Class Name | Description |
| --- | --- | --- |
| class-SideBar.php | `SideBar` | Slide-out panel providing hierarchical info, logs, and search via Ajax. |
| class-TitleRenderer.php | `TitleRenderer` | Specialized renderer for breadcrumbs and hierarchical title displays. |

</details>

---

## Development Environment

* **OS**: Windows 10/11 (XAMPP for Windows)
* **PHP**: 8.1.25 or higher (Required)
* **WordPress**: 6.0 or higher recommended
* **Base Theme**: [_0 (Underscores)](https://underscores.me/)

---

## Installation

1. Place the parent theme (`kasax`) into the `wp-content/themes/` directory.
2. Upload this child theme folder (`kasax_child`) to `wp-content/themes/`.
3. Activate "kasax Child" from the WordPress Admin Dashboard under [Appearance] > [Themes].
4. (Recommended) Create `paths.json` by referencing `paths.json.example` and adjust the paths to your environment.

---

## License

This project is licensed under the [GNU General Public License v2 or later](https://www.gnu.org/licenses/gpl-2.0.html), consistent with the parent theme.

---

## Credits

* **Customized by**: [yusuke-kasai-kasouya](https://github.com/yusuke-kasai-kasouya/)
* **Base Theme**: [_0 (Underscores)](https://underscores.me/) by Automattic

---

### Communication

The author is a native Japanese speaker. While inquiries in English are welcome (via translation tools), communication in Japanese will allow for smoother responses.

> **Note:** This document was prepared with the assistance of AI (Gemini) to accurately reflect the internal code structure. Full-width brackets (`［ ］`) are intentionally used for shortcode representations to prevent unintended evaluation.
