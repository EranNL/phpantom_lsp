# PHPantom — Bug Fixes

Every bug below must be fixed at its root cause. "Detect the
symptom and suppress the diagnostic" is not an acceptable fix.
If the type resolution pipeline produces wrong data, fix the
pipeline so it produces correct data. Downstream consumers
(diagnostics, hover, completion, definition) should never need
to second-guess upstream output.

Each entry below carries an **Impact · Complexity** rating using the same
scale defined in [`docs/todo.md`](../todo.md), but a bug's row lives
**here only** — do not add or link a bug entry to `docs/todo.md`'s sprint
or backlog tables. This file is its own list, not a domain document
sprint items draw from: whenever it holds anything, that is actively
addressed, independently of sprint planning.

Bugs land here from wherever they surface: found while working on another
task, or sweeps of the sample projects under `projects/`. Entries are
grouped by the mechanism that has to change, not by the symptom that
surfaced: one entry is one root cause, however many shapes it shows up in.

## Crashes

No outstanding items.

## Type comparison

No outstanding items.

## Standard-library return types

No outstanding items.

## Reachability

No outstanding items.

## Narrowing

No outstanding items.

## Arithmetic

No outstanding items.

## Symbol resolution

No outstanding items.

## Array types

No outstanding items.

## Laravel

No outstanding items.

## Blade

### B544. Blade directives are recognized whether or not the installed Laravel has them

**Impact: Medium · Complexity: Medium**

```blade
<script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList"
    }
</script>
```

On Laravel 8 this is plain text: Blade only compiles an `@name` it has a
`compileName()` method (or a registered custom directive) for, and
`@context` arrived with `CompilesContexts` in Laravel 11. The preprocessor
lowers every name in the fixed list in `blade/directives.rs` regardless of
version, so `"@context":` opens an `if` block and the rest of the JSON-LD
reports a cascade of syntax errors (around 200 in one Laravel 8 project,
from schema.org snippets alone). The recognized set should come from the
installed compiler, by reading which `compile*` methods
`Illuminate\View\Compilers\BladeCompiler` and its `Concerns` traits
actually define, the way the alias tables are read from the installed
framework, with the fixed list as the fallback when no framework is
installed.

### B545. A `:name="…"` attribute on a plain HTML tag is lowered as PHP

**Impact: Low-Medium · Complexity: Low**

```blade
<span :class="{'is-empty': !selected?.text}"></span>
```

Blade only evaluates `:name` attributes on `<x-…>` component tags; on any
other tag the attribute is client-side (Alpine, Vue) and Blade emits it
verbatim. `tag::bound_attr` in `blade/preprocessor/tag.rs` fires on
`html.in_tag` alone, so the JavaScript object literal is parsed as PHP and
reports a run of syntax errors per attribute. The check needs to require
that the enclosing tag is a component tag.

## Templates

No outstanding items.

## Miscellaneous

### B546. `analyze` re-runs the Blade refresh on a diagnostic worker while other workers diagnose templates

**Impact: Low-Medium · Complexity: Medium**

`analyse::stages::index_project` parses the user files and runs
`refresh_blade_injected_vars` itself, but never publishes
`workspace_indexed`. The first diagnostic that asks for every user file's
symbol map (`enumerate_all_routes` through `user_file_symbol_maps`) then
runs the editor's `ensure_workspace_indexed` from inside a diagnostic
worker: it walks the workspace, indexes the resource files, and refreshes
every template a second time, re-parsing the ones whose inferred variables
changed, while the other workers are diagnosing those same templates. Which
state a template is checked against depends on timing. On one Laravel 8
project the CLI reports 16 fewer diagnostics than a run where the refresh
happens once, after the resource indexing, which is what the editor sees.

Calling `ensure_workspace_index_ready_with_progress` from `index_project` in
place of the bare refresh makes the run deterministic and costs nothing on a
full run, but a run limited to a few paths then parses the whole workspace
(about 0.1s and 70-80 MB more on a single file). The fix should index only
what the selected files' diagnostics need, or settle the index before the
diagnostic pass starts in a way a path-limited run can afford.
