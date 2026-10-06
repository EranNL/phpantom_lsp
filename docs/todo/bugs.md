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

### B547. A function a watched file adds can stay "not found" until the next change

**Impact: Low · Complexity: Medium**

`reindex_files_batch` (`src/lib.rs`) clears `function_not_found_cache`
before it adds the batch's functions to `autoload_function_index`. A
lookup running on another thread (a diagnostic pass, a hover) that read the
index before the insert and records its miss after the clear writes a miss
for a function the batch just declared. `find_or_load_function`
(`src/resolution.rs`) re-checks only `global_functions` under the cache's
write lock, which a byte-scanned file never reaches, and every later lookup
stops at the negative cache before the index check, so the call is
reported as an undefined function until the next watched-file change or a
re-parse of the declaring file. Clear the cache after the index inserts,
and record a miss only when no index write happened since the lookup
started (a generation counter compared under the cache's write lock).

## Array types

No outstanding items.

## Laravel

No outstanding items.

## Blade

### B550. A template's first import lands inside the block its first line opens

**Impact: Medium · Complexity: Medium**

```blade
@if ($user)
    {{ Carbon::now() }}
@endif
```

Completing `Carbon` writes `@use('Carbon\Carbon')` after the first line,
inside the `@if`. Blade compiles `@use` to a PHP `use` statement where it
stands, and PHP rejects one inside a block, so the view no longer compiles.
An import that sorts before every existing `@use` goes to the template's
start only when that start survives the trip through the source map
(`analyze_template_use_block`, `src/blade/use_block.rs`); a first line
that opens with `{{`, a directive, or a component tag does not, because a
Blade position at the start of a token maps to the end of the PHP it
lowers to, so the import moves after the line instead. The start of the
virtual line maps back to the template's start for such a line (it is
where the generated PHP begins), so the import can be planned there. A
template that opens with `@php` or `<?php` takes the import as a PHP `use`
just inside that block, and only a leading directive that lowers to
nothing at all (`@use`, `@inject`) leaves the end of the first line as the
place. BL1's "Import class" action writes its import through the same
code.

### B551. An unused `@use` import in a template is never reported

**Impact: Low · Complexity: Medium**

`collect_unused_import_diagnostics` reads the virtual PHP, where the
preprocessor hoisted each `@use` directive into the prologue as a real
`use` statement. The diagnostic's range lands in the prologue, which maps
to no template position, so it is dropped: a `use` inside `@php` is
reported, a `@use` directive never is. Report it at the directive's own
range (the scanner in `src/blade/use_directive.rs` knows where each one
is), and teach "Remove unused import" to delete the directive.

## Templates

No outstanding items.

## Miscellaneous

### B548. Moving a class rewrites the wrong `namespace` block when two blocks declare the same short name

**Impact: Low · Complexity: Low**

```php
<?php
namespace A { class Foo {} }
namespace B { class Foo {} }
```

Moving `B\Foo` to `C\Foo` rewrites `namespace A`. The move finds the class
being moved as the first `ClassDeclaration` span with its short name
(`src/rename/class/mod.rs`) and then takes the namespace declaration before
that span, without checking that the class it found is the one in the
namespace being moved. Match the declaration inside the old namespace's
block.

### B549. `@throws` and namespaced-function completions plan their import against the whole file

**Impact: Low · Complexity: Low-Medium**

The `@throws` smart items (`src/completion/phpdoc/mod.rs`), the `@throws`
imports of docblock generation (`build_throws_import_edits` in
`src/completion/phpdoc/generation/mod.rs`) and `build_function_completions`
(`src/completion/context/function_completion.rs`) build their `use` edit
from `analyze_use_block(content)`, the whole file. In a file with several
`namespace` blocks the import can land in another block, which every other
import edit stopped doing this cycle; in a Blade template it lands in the
virtual prologue, so the completion carrying it is dropped. Plan them
through `Backend::use_block_for` with the block the cursor is in, as class
completion does.
