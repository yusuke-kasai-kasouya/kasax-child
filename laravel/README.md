# kasax_child : Search Logic Unit (Laravel Backend)

This directory contains the Laravel-based backend logic designed to enhance the search capabilities of the "kasax_child" creative support system.

## 🏗 Architecture: Decoupled Hybrid Design
This system uses WordPress as a "Data Storage" while offloading complex calculations and search processes to Laravel.

* **Laravel's Role**: A specialized engine for high-load search logic that is difficult to process within WordPress.
* **Independence**: The WordPress theme functions independently. Connecting this unit enables faster and more flexible knowledge discovery.

## 🧩 Core Components

### A. Search Engine (Service & Repository)
* **`KxKnowledgeSearchService.php`**: The heart of the system. Handles business logic including keyword weighting, taxonomy filtering (include/exclude), and Regex-based text processing.
* **`KxPostRepository.php`**: A data access layer specialized for the `wp_posts` table. It utilizes Laravel's Query Builder to perform flexible data extraction beyond the limits of standard `WP_Query`.

### B. API Gateway (Controller & Middleware)
* **`KxSearchApiController.php`**: Receives requests from WordPress via JSON. Processes and delivers data in formats optimized for the frontend (e.g., ID identification by exact title match).
* **`IpLimitMiddleware.php`**: Security layer. Restricts access to authorized IP addresses to prevent unauthorized data retrieval.

### C. Search Simulator (Web & Blade)
* Includes built-in UI tools (`search_form.blade.php`) to visually test and verify search logic independently from the WordPress frontend.

## 🚀 Future Scalability
While currently focused on search, this architecture serves as an **expansion slot** for future creative support features:
* AI-powered text analysis and plot inconsistency detection.
* Automated tagging and relationship mapping of creative assets.

---
*Note: This technical summary was drafted with AI assistance (Gemini) to accurately reflect the internal code structure.*

---
Communication
Please note that the author is a native Japanese speaker and is still learning English. I use translation tools to communicate globally, so thank you for your understanding!