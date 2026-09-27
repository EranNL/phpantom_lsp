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

### B542. Import edits treat a file with several `namespace` blocks as having one `use` list

**Impact: Low · Complexity: Medium**

```php
namespace A {
    use X\Foo;
    function a(): Foo {}
}
namespace B {
    // `B\Foo` is unknown, but block A's import makes `Foo` look
    // already imported, so no "import class" action is offered.
    function b(Foo $f) {}
}
```

Name resolution is per block (each `NamespaceSpan` carries its own
`use_map`), but everything that *writes* imports still works on the
file-wide `file_imports` table and `first_file_namespace`: the import-class
and qualified-name-to-import code actions, the PHPStan `add_throws` /
`add_override` / `remove_throws` fixes, and class rename/move rewriting
(`rename/class/rewrite.rs`). They check for an existing import against every
block's imports at once, and work out where a new one goes from the first
block, so an edit in a later block is skipped because another block
imports the name, or is placed against the wrong block. Fixing it means locating the target block by offset and
reading, inserting into, and rewriting that block's own `use` statements.

### B543. Unused-import detection pools the imports of every `namespace` block

**Impact: Low · Complexity: Medium**

```php
namespace A {
    use X\Foo;              // unused in A, but not reported
}
namespace B {
    use X\Foo;
    function b(Foo $f) {}
}
```

`diagnostics/unused_imports.rs` checks declared imports against the merged
file-wide import table, so an import used only in another block counts as
used, and two blocks importing the same alias collapse into one entry.
It needs to track each block's imports (and their source ranges) and match
references against the block they are written in.

## Array types

No outstanding items.

## Laravel

No outstanding items.

## Blade

No outstanding items.

## Templates

No outstanding items.

## Miscellaneous

### B530. A constant whose initializer uses another constant has no type

**Impact: Medium · Complexity: Medium**

```php
const ONE = 1;
const TWO = ONE * 2;           // no type, should be 2

class C {
    const ONE = 1;
    const THREE = 3;
    const ONE_THIRD = self::ONE / self::THREE;               // no type, should be float
    const SENTENCE = 'The value of THREE is ' . self::THREE; // no type
}

class Child extends Base {
    const A = [...parent::KEYS, 'c' => 'c'];                 // array, should be the shape
}
```

`self::ONE * 2` and `self::ONE >> 2` in a class constant fold, but
division, concatenation and a spread do not, and a global constant's
initializer that names another constant does not fold at all.

The SKIPs are in `tests/psalm_assertions/php56.php` and
`array_assignment.php`. Found porting Psalm's `Php56Test.php` and
`ArrayAssignmentTest.php`.
