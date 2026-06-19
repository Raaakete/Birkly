# P2-M4 DnD failure modes (baseline)

Documented during P2-M4-T1 from P2-M1 review and code audit.

| Scenario | Symptom | Root cause | Fix (P2-M4) |
|----------|---------|------------|-------------|
| Group reorder | Ungrouped block shifts index | `SimpleDragDrop` counted `.ungrouped` in group list | Exclude `.ungrouped` from group sorter selector |
| Cross-group move | Drop ignored or wrong target | Mouse-based manager + conflicting handlers | Migrate to `NestedDragDropManager` + `SimpleDragDrop` |
| Same-group reorder | Indicator flicker / no save | Cross-container guards in mouse manager | Per-group `SimpleDragDrop` instances |
| Unknown API action | `{message:"Content API is working"}` | `content.php` default handler | Return 400 for unknown `action` |
| Groups API | No `list_groups` | Only `save_groups` existed | Add `list_groups` + `core/collection_groups.php` |
| Console noise | Debug logs during drag | `simple-drag-drop.js` / `nested-drag-drop.js` | Remove debug `console.log` |

## Verification scenarios (P2-M4-T4)

1. Reorder two custom groups — refresh — order persists  
2. Reorder collections inside one group — refresh — order persists  
3. Move collection from group A to group B (or ungrouped) — refresh — membership persists  
