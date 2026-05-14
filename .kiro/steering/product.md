# Product Overview

**Geomin Smart Repository (GSR)** is an internal document management system for PT Antam (a mining company). It manages controlled documents in compliance with ISO quality management standards.

## Core Domain

- Document lifecycle management (creation, revision, publishing, obsolescence)
- ISO clause mapping — documents are linked to relevant ISO clauses
- Revision control with PDF/Word file storage and text extraction
- Department and category-based document organization
- QR code generation for physical document traceability
- Version comparison between document revisions

## Users & Roles

- **admin** — Full access to all features
- **manajemen** — Can edit documents and download files
- **Regular users** — Read-only access, can view/preview published documents

## Language

The UI is in **Bahasa Indonesia**. All labels, navigation groups, and user-facing text use Indonesian. Code (variables, classes, methods) is in English.

## Key Business Rules

- When a revision is published (`Published` / `Terbit`), all other published revisions of the same document are automatically marked `Obsolete`
- PDF text is extracted asynchronously via a queued job when a PDF revision is uploaded
- Documents have a configurable retention period (default 36 months)
- Documents can be internal or external (`is_external` flag)
- File downloads (PDF/Word) are restricted to admin and manajemen roles
- **Visual Diff UI:** Version comparison utilizes a `CompareVersions` custom page with strictly configured CSS (fixed table-layout, explicit max-width, and break-word) to prevent text overflow issues caused by long string of dots in document table of contents. The agent MUST NOT alter the CSS structure in `compare-versions.blade.php` without explicit user instruction.
