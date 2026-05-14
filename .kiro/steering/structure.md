# Project Structure

## Filament Resource Pattern

Resources follow a **domain-grouped folder structure** under `app/Filament/Resources/`:

```
app/Filament/Resources/{DomainPlural}/
├── {Domain}Resource.php        # Main resource class (form, table, infolist, pages)
├── Pages/                      # independent custom page (example: CompareVersions.php)
│   ├── List{Domain}s.php
│   ├── Create{Domain}.php
│   ├── Edit{Domain}.php
│   └── View{Domain}.php        # (optional)
├── Schemas/
│   └── {Domain}Form.php        # Reusable form schema (generated scaffold, may be unused)
├── Tables/
│   └── {Domain}sTable.php      # Reusable table config (generated scaffold, may be unused)
└── RelationManagers/           # (optional)
    └── {Relation}RelationManager.php
```

The main `Resource.php` file typically contains the full `form()`, `table()`, and `infolist()` definitions inline. The `Schemas/` and `Tables/` folders contain generated scaffolds that may not be actively used.

## Models

`app/Models/` — Eloquent models. Key models:

| Model             | Purpose                                   |
| ----------------- | ----------------------------------------- |
| Document          | Core entity — a controlled document       |
| DocumentRevision  | Version/revision of a document (has file) |
| DocumentCategory  | Classification category                   |
| Department        | Organizational unit                       |
| IsoClause         | ISO standard clause reference             |
| DocumentIsoClause | Pivot for Document ↔ IsoClause            |
| User              | Authentication & role-based access        |

## Policies

`app/Policies/` — Authorization policies per resource. Role-based (`admin`, `manajemen`).

## Jobs

`app/Jobs/` — Queued jobs. Currently: `ExtractPdfTextJob` for async PDF text extraction.

## Routes

- `routes/web.php` — Custom routes for PDF streaming and secure document viewer
- Filament handles all admin panel routes automatically via resource discovery

## Views

- `resources/views/` — Blade templates (e.g., `secure-viewer.blade.php` for full-screen PDF viewer)

## Key Directories

```
app/
├── Filament/          # Admin panel (resources, pages, widgets)
├── Http/Controllers/  # Minimal — most logic is in Filament
├── Jobs/              # Queue jobs
├── Models/            # Eloquent models
├── Policies/          # Authorization
└── Providers/         # Service & panel providers

database/
├── migrations/        # Schema definitions
├── factories/         # Model factories
└── seeders/           # Database seeders

resources/
├── css/               # Tailwind entry point
├── js/                # JS entry point
└── views/             # Blade templates
```

## Navigation Groups

Filament panel uses two navigation groups:

- **Manajemen Dokumen** — Document resource
- **Data Master** — Departments, Categories, ISO Clauses
