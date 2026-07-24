# Studio canvas v2 schema

```json
{
  "version": 2,
  "page": { "slug": "index", "title": "Home", "sort": 0 },
  "breakpoint": "default",
  "breakpoints": {
    "default": { "minWidth": 0 },
    "tablet": { "minWidth": 768 },
    "desktop": { "minWidth": 1024 }
  },
  "designTokens": {
    "colors": { "primary": "#0d6efd" },
    "spacing": { "md": "1rem" }
  },
  "sections": [
    {
      "id": "sec-1",
      "classes": ["studio-section"],
      "layout": { "type": "grid", "columns": 3, "gap": "1rem" },
      "responsive": {
        "tablet": { "columns": 2 },
        "default": { "columns": 1 }
      },
      "rows": [
        {
          "id": "row-1",
          "columns": [
            {
              "id": "col-1",
              "width": "33%",
              "elements": [
                { "id": "el-1", "type": "text", "tag": "h1", "content": "Title", "styles": {}, "classes": [] }
              ]
            }
          ]
        }
      ]
    }
  ],
  "customClasses": {}
}
```

v1 projects (`version` missing or `1`) upgrade automatically: add `version: 2`, `breakpoint: default`, empty `breakpoints`/`designTokens`.
