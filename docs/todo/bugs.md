# PHPantom — Bug Fixes

Every bug below must be fixed at its root cause. "Detect the
symptom and suppress the diagnostic" is not an acceptable fix.
If the type resolution pipeline produces wrong data, fix the
pipeline so it produces correct data. Downstream consumers
(diagnostics, hover, completion, definition) should never need
to second-guess upstream output.

Each entry below carries an **Impact · Complexity** rating using the same
scale defined in [`docs/todo.md`](../todo.md); that table is also where
each bug's row lives in the current sprint/backlog.

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

No outstanding items.

## Templates

### B463. A method template with a bound is shown as its bound inside the method
**Impact: Low · Complexity: High**

```php
/** @template T of A&B @param iterable<T> $items */
function f($items) {
    foreach ($items as $item) {
        $item; // should be T, is A&B
    }
}
```

An unbounded method template, and a class template, keep their name inside the body (`T`); a method template with a bound is replaced by the bound when the parameter is seeded (`substitute_template_param_bounds` and `substitute_class_string_template_bounds`, called from `resolve_param_type` in `forward_walk/param_seeding.rs`). The value is still `T`, so hover should show `T`, and a value returned or passed on should carry the template rather than the bound. A method template bounded by a class template (`@template F of E`) shows the class template's name `E` instead of `F`.

Dropping the substitution alone is not a fix, because the substitution is what gives these values their members. A class template named in a type resolves to classes through its owning class's bounds (the fallback at the end of `type_hint_to_classes_typed_depth` in `type_engine/types/resolution.rs`). Nothing records a method template's bound where a later lookup can reach it, so with the bare name, member access on anything derived from the parameter stops working: `$item->` in the example above, `$res[$position]->` for an `array<T>`. Some of those lookups run after the body walk has finished (completion re-resolving `array<T>`'s element), so a thread-local pushed while the walk is running does not cover them either.

What needs doing: make the bound travel with the type. The likely shape is a new `TypeKind` for a bounded template parameter (name plus bound), modelled on how `StaticType` carries its class:

- It displays as the template name, so hover and the assertion runner see `T`.
- It resolves to classes, and is judged by subtyping and narrowing, as its bound (`is_string($t)` on `T of int|string` narrows like the bound does).
- `substitute()` replaces it by name like `Named`, so call-site template substitution keeps working.
- Parameter seeding produces it instead of substituting the bound.

Every `match` on `TypeKind` needs checking for the new arm; the ones that list `StaticType` explicitly (about 15 files) are the place to start.

Done when the `// SKIP`'d assertions below pass, completion and unknown-member diagnostics on bounded method template values behave as they do today, and the full suite passes.

Found porting PHPStan's `Rules/Methods/data/bug-7511.php`, `Rules/Methods/data/bug-5562.php`, `Rules/Generics/data/bug-3769.php`, `Rules/PhpDoc/data/bug-4643.php` and `Rules/Functions/data/bug-7823.php`; the assertions are `// SKIP` in the ported copies under `tests/phpstan_data/`.

## Miscellaneous

No outstanding items.
