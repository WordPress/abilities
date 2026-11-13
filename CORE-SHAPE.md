# Core Abilities Schema Quick Reference

This document is the source of truth for the shape of the core abilities:
their names, input and output schemas, and behavior. Where the package code
differs, the code is updated to match it.

| Ability | Status | Category | readonly / destructive / idempotent | REST transport method |
|---|---|---|---|---|
| `core/content-query` | Landed, 213ecca2e9 | content | true / false / true | GET |
| `core/content-create` | Proposed | content | false / false / false | POST |
| `core/content-update` | Proposed | content | false / false / true | POST |
| `core/content-delete` | Proposed | content | false / true / true | DELETE |
| `core/settings-get` | Landed, b6512b1e8c | site | true / false / true | GET |
| `core/settings-update` | Proposed | site | false / false / true | POST |
| `core/users-query` | Landed, 39981fd0fe | user | true / false / true | GET |
| `core/user-create` | Proposed | user | false / false / false | POST |
| `core/user-update` | Proposed | user | false / false / true | POST |
| `core/user-delete` | Proposed | user | false / true / true | DELETE |
| `core/media-query` | Proposed | content | true / false / true | GET |
| `core/media-upload` | Proposed | content | false / false / false | POST |
| `core/media-update` | Proposed | content | false / false / true | POST |
| `core/media-delete` | Proposed | content | false / true / true | DELETE |
| `core/terms-query` | Proposed | content | true / false / true | GET |
| `core/term-create` | Proposed | content | false / false / false | POST |
| `core/term-update` | Proposed | content | false / false / true | POST |
| `core/term-delete` | Proposed | content | false / true / true | DELETE |

Errors are `WP_Error` objects. Each "Example N error" block shows one as the REST response body (`code`, `message`, `data`); errors are not validated against the output schema.

## Content abilities

### `core/content-query`

Example 1 input, the drafts of the `post` type with chosen fields:

```json
{ "where": { "type": "post", "status": [ "draft" ] }, "fields": [ "title", "date", "author" ] }
```

Example 1 output:

```json
{
  "items": [
    {
      "id": 123,
      "date": { "site": "2026-10-21T09:30:00+01:00", "gmt": "2026-10-21T08:30:00+00:00" },
      "title": { "raw": "Launch notes", "rendered": "Launch notes" },
      "author": { "id": 42, "name": "Maya Carter", "slug": "mcarter" }
    }
  ],
  "pagination": { "total": 1, "total_pages": 1 }
}
```

Example 2 input, the child pages of page 7, two per page, with the default fields:

```json
{ "where": { "type": "page", "parent": 7 }, "pagination": { "per_page": 2 } }
```

Example 2 output:

```json
{
  "items": [
    {
      "id": 126,
      "type": "page",
      "status": "publish",
      "date": { "site": "2026-10-21T09:40:00+01:00", "gmt": "2026-10-21T08:40:00+00:00" },
      "slug": "pricing",
      "title": { "rendered": "Pricing" }
    },
    {
      "id": 118,
      "type": "page",
      "status": "publish",
      "date": { "site": "2026-10-02T11:00:00+01:00", "gmt": "2026-10-02T10:00:00+00:00" },
      "slug": "features",
      "title": { "rendered": "Features" }
    }
  ],
  "pagination": { "total": 3, "total_pages": 2 }
}
```

Example 3 input, `parent` on a flat type:

```json
{ "where": { "type": "post", "parent": 7 } }
```

Example 3 error:

```json
{
  "code": "content_invalid_filter",
  "message": "The parent filter is only supported for hierarchical post types.",
  "data": { "status": 400, "params": { "where[parent]": "The parent filter is only supported for hierarchical post types." } }
}
```

Input schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "where" ],
  "properties": {
    "where": {
      "type": "object",
      "additionalProperties": false,
      "required": [ "type" ],
      "properties": {
        "type":   { "type": "string", "enum": [ "post", "page" ] },
        "ids":    { "type": "array", "uniqueItems": true, "minItems": 1, "maxItems": 100, "items": { "type": "integer", "minimum": 1 } },
        "slugs":  { "type": "array", "uniqueItems": true, "minItems": 1, "maxItems": 100, "items": { "type": "string", "minLength": 1 } },
        "status": { "type": "array", "uniqueItems": true, "minItems": 1, "items": { "type": "string", "enum": [ "publish", "future", "draft", "pending", "private" ] } },
        "author": {
          "type": "object",
          "additionalProperties": false,
          "minProperties": 1,
          "properties": {
            "id":   { "type": "integer", "minimum": 1 },
            "slug": { "type": "string", "minLength": 1 }
          }
        },
        "parent": { "type": "integer", "minimum": 0 }
      }
    },
    "fields": {
      "type": "array",
      "uniqueItems": true,
      "items": {
        "type": "string",
        "enum": [
          "id", "type", "status", "date", "date.site", "date.gmt", "modified", "modified.site",
          "modified.gmt", "slug", "link", "title", "title.raw", "title.rendered", "excerpt",
          "excerpt.raw", "excerpt.rendered", "excerpt.protected", "content", "content.raw",
          "content.rendered", "content.protected", "author", "author.id", "author.name",
          "author.slug", "parent"
        ]
      }
    },
    "pagination": {
      "type": "object",
      "additionalProperties": false,
      "properties": { "page": { "type": "integer", "minimum": 1 }, "per_page": { "type": "integer", "minimum": 1, "maximum": 100 } }
    }
  }
}
```

Default `fields`: `id, type, status, date, slug, title.rendered`.

Trashed posts cannot be read today: `where.status` has no `trash` value. It gets one when `core/content-delete` is added.

Output schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "items", "pagination" ],
  "properties": {
    "items": {
      "type": "array",
      "items": {
        "type": "object",
        "additionalProperties": false,
        "required": [ "id" ],
        "properties": {
          "id":      { "type": "integer" },
          "type":    { "type": "string" },
          "status":  { "type": "string" },
          "date": {
            "type": "object",
            "additionalProperties": false,
            "properties": { "site": { "type": [ "string", "null" ], "format": "date-time" }, "gmt": { "type": [ "string", "null" ], "format": "date-time" } }
          },
          "modified": {
            "type": "object",
            "additionalProperties": false,
            "properties": { "site": { "type": [ "string", "null" ], "format": "date-time" }, "gmt": { "type": [ "string", "null" ], "format": "date-time" } }
          },
          "slug":    { "type": "string" },
          "link":    { "type": "string", "format": "uri" },
          "title":   { "type": "object", "additionalProperties": false, "properties": { "raw": { "type": "string" }, "rendered": { "type": "string" } } },
          "excerpt": { "type": "object", "additionalProperties": false, "properties": { "raw": { "type": "string" }, "rendered": { "type": "string" }, "protected": { "type": "boolean" } } },
          "content": { "type": "object", "additionalProperties": false, "properties": { "raw": { "type": "string" }, "rendered": { "type": "string" }, "protected": { "type": "boolean" } } },
          "author": {
            "type": "object",
            "additionalProperties": false,
            "properties": { "id": { "type": "integer" }, "name": { "type": [ "string", "null" ] }, "slug": { "type": [ "string", "null" ] } }
          },
          "parent":  { "type": "integer" }
        }
      }
    },
    "pagination": {
      "type": "object",
      "additionalProperties": false,
      "required": [ "total", "total_pages" ],
      "properties": { "total": { "type": "integer" }, "total_pages": { "type": "integer" } }
    }
  }
}
```

### `core/content-create`

Example 1 input, a draft with the default fields:

```json
{ "data": { "type": "post", "title": "Launch notes" } }
```

Example 1 output:

```json
{
  "id": 123,
  "type": "post",
  "status": "draft",
  "date": { "site": "2026-10-21T09:30:00+01:00", "gmt": "2026-10-21T08:30:00+00:00" },
  "slug": "",
  "title": { "rendered": "Launch notes" }
}
```

Example 2 input, a published child page with chosen fields:

```json
{
  "data": {
    "type": "page",
    "title": "Pricing",
    "content": "<!-- wp:paragraph -->\n<p>Plans start at 9 euro per month.</p>\n<!-- /wp:paragraph -->",
    "status": "publish",
    "parent": 7,
    "slug": "pricing"
  },
  "fields": [ "status", "link", "title.rendered", "content.rendered", "parent" ]
}
```

Example 2 output:

```json
{
  "id": 126,
  "status": "publish",
  "link": "https://example.test/products/pricing/",
  "title": { "rendered": "Pricing" },
  "content": { "rendered": "<p>Plans start at 9 euro per month.</p>\n" },
  "parent": 7
}
```

Example 3 input, an attribute the post type does not support:

```json
{ "data": { "type": "post", "title": "Launch notes", "parent": 7 } }
```

Example 3 error:

```json
{
  "code": "content_invalid_attribute",
  "message": "Invalid attribute: parent.",
  "data": { "status": 400, "params": { "data[parent]": "The post type is not hierarchical." } }
}
```

Input schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "data" ],
  "properties": {
    "data": {
      "type": "object",
      "additionalProperties": false,
      "required": [ "type" ],
      "properties": {
        "type":    { "type": "string", "enum": [ "post", "page" ] },
        "title":   { "type": "string" },
        "content": { "type": "string" },
        "excerpt": { "type": "string" },
        "status":  { "type": "string", "enum": [ "publish", "future", "draft", "pending", "private" ] },
        "slug":    { "type": "string", "minLength": 1 },
        "date": {
          "type": "object",
          "additionalProperties": false,
          "minProperties": 1,
          "properties": { "site": { "type": "string", "format": "date-time" }, "gmt": { "type": "string", "format": "date-time" } }
        },
        "author":  { "type": "integer", "minimum": 1 },
        "parent":  { "type": "integer", "minimum": 0 }
      }
    },
    "fields": {
      "type": "array",
      "uniqueItems": true,
      "items": {
        "type": "string",
        "enum": [
          "id", "type", "status", "date", "date.site", "date.gmt", "modified", "modified.site",
          "modified.gmt", "slug", "link", "title", "title.raw", "title.rendered", "excerpt",
          "excerpt.raw", "excerpt.rendered", "excerpt.protected", "content", "content.raw",
          "content.rendered", "content.protected", "author", "author.id", "author.name",
          "author.slug", "parent"
        ]
      }
    }
  }
}
```

Default `fields`: `id, type, status, date, slug, title.rendered`.

`data.date` takes `site`, `gmt` or both; `site` wins when both are sent.

Output schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "id" ],
  "properties": {
    "id":      { "type": "integer" },
    "type":    { "type": "string" },
    "status":  { "type": "string" },
    "date": {
      "type": "object",
      "additionalProperties": false,
      "properties": { "site": { "type": [ "string", "null" ], "format": "date-time" }, "gmt": { "type": [ "string", "null" ], "format": "date-time" } }
    },
    "modified": {
      "type": "object",
      "additionalProperties": false,
      "properties": { "site": { "type": [ "string", "null" ], "format": "date-time" }, "gmt": { "type": [ "string", "null" ], "format": "date-time" } }
    },
    "slug":    { "type": "string" },
    "link":    { "type": "string", "format": "uri" },
    "title":   { "type": "object", "additionalProperties": false, "properties": { "raw": { "type": "string" }, "rendered": { "type": "string" } } },
    "excerpt": { "type": "object", "additionalProperties": false, "properties": { "raw": { "type": "string" }, "rendered": { "type": "string" }, "protected": { "type": "boolean" } } },
    "content": { "type": "object", "additionalProperties": false, "properties": { "raw": { "type": "string" }, "rendered": { "type": "string" }, "protected": { "type": "boolean" } } },
    "author": {
      "type": "object",
      "additionalProperties": false,
      "properties": { "id": { "type": "integer" }, "name": { "type": [ "string", "null" ] }, "slug": { "type": [ "string", "null" ] } }
    },
    "parent":  { "type": "integer" }
  }
}
```

### `core/content-update`

Example 1 input, a new title and excerpt:

```json
{
  "where": { "type": "post", "id": 123 },
  "data": { "title": "Launch notes, updated", "excerpt": "What shipped and what is next." },
  "fields": [ "modified", "title", "excerpt" ]
}
```

Example 1 output:

```json
{
  "id": 123,
  "modified": { "site": "2026-10-21T10:05:00+01:00", "gmt": "2026-10-21T09:05:00+00:00" },
  "title": { "raw": "Launch notes, updated", "rendered": "Launch notes, updated" },
  "excerpt": { "raw": "What shipped and what is next.", "rendered": "<p>What shipped and what is next.</p>\n", "protected": false }
}
```

Example 2 input, publishing the draft:

```json
{ "where": { "type": "post", "id": 123 }, "data": { "status": "publish" }, "fields": [ "status", "slug", "link" ] }
```

Example 2 output:

```json
{ "id": 123, "status": "publish", "slug": "launch-notes-updated", "link": "https://example.test/launch-notes-updated/" }
```

Example 3 input, a post the user cannot edit:

```json
{ "where": { "type": "post", "id": 123 }, "data": { "title": "Launch notes" } }
```

Example 3 error:

```json
{ "code": "content_cannot_edit", "message": "Sorry, you are not allowed to edit this post.", "data": { "status": 403 } }
```

Input schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "where", "data" ],
  "properties": {
    "where": {
      "type": "object",
      "additionalProperties": false,
      "required": [ "type", "id" ],
      "properties": { "type": { "type": "string", "enum": [ "post", "page" ] }, "id": { "type": "integer", "minimum": 1 } }
    },
    "data": {
      "type": "object",
      "additionalProperties": false,
      "minProperties": 1,
      "properties": {
        "title":   { "type": "string" },
        "content": { "type": "string" },
        "excerpt": { "type": "string" },
        "status":  { "type": "string", "enum": [ "publish", "future", "draft", "pending", "private" ] },
        "slug":    { "type": "string", "minLength": 1 },
        "date": {
          "type": "object",
          "additionalProperties": false,
          "minProperties": 1,
          "properties": { "site": { "type": "string", "format": "date-time" }, "gmt": { "type": "string", "format": "date-time" } }
        },
        "author":  { "type": "integer", "minimum": 1 },
        "parent":  { "type": "integer", "minimum": 0 }
      }
    },
    "fields": {
      "type": "array",
      "uniqueItems": true,
      "items": {
        "type": "string",
        "enum": [
          "id", "type", "status", "date", "date.site", "date.gmt", "modified", "modified.site",
          "modified.gmt", "slug", "link", "title", "title.raw", "title.rendered", "excerpt",
          "excerpt.raw", "excerpt.rendered", "excerpt.protected", "content", "content.raw",
          "content.rendered", "content.protected", "author", "author.id", "author.name",
          "author.slug", "parent"
        ]
      }
    }
  }
}
```

Default `fields`: `id, type, status, date, slug, title.rendered`.

`data.date` takes `site`, `gmt` or both; `site` wins when both are sent.

Output schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "id" ],
  "properties": {
    "id":      { "type": "integer" },
    "type":    { "type": "string" },
    "status":  { "type": "string" },
    "date": {
      "type": "object",
      "additionalProperties": false,
      "properties": { "site": { "type": [ "string", "null" ], "format": "date-time" }, "gmt": { "type": [ "string", "null" ], "format": "date-time" } }
    },
    "modified": {
      "type": "object",
      "additionalProperties": false,
      "properties": { "site": { "type": [ "string", "null" ], "format": "date-time" }, "gmt": { "type": [ "string", "null" ], "format": "date-time" } }
    },
    "slug":    { "type": "string" },
    "link":    { "type": "string", "format": "uri" },
    "title":   { "type": "object", "additionalProperties": false, "properties": { "raw": { "type": "string" }, "rendered": { "type": "string" } } },
    "excerpt": { "type": "object", "additionalProperties": false, "properties": { "raw": { "type": "string" }, "rendered": { "type": "string" }, "protected": { "type": "boolean" } } },
    "content": { "type": "object", "additionalProperties": false, "properties": { "raw": { "type": "string" }, "rendered": { "type": "string" }, "protected": { "type": "boolean" } } },
    "author": {
      "type": "object",
      "additionalProperties": false,
      "properties": { "id": { "type": "integer" }, "name": { "type": [ "string", "null" ] }, "slug": { "type": [ "string", "null" ] } }
    },
    "parent":  { "type": "integer" }
  }
}
```

### `core/content-delete`

Example 1 input, trashing a post:

```json
{ "where": { "type": "post", "id": 123 } }
```

Over REST:

```http
DELETE /wp-json/wp-abilities/v1/abilities/core/content-delete/run?input[where][type]=post&input[where][id]=123
```

Example 1 output:

```json
{ "outcome": "trashed" }
```

Example 2 input, deleting a page permanently:

```json
{ "where": { "type": "page", "id": 126 }, "permanent": true }
```

Example 2 output:

```json
{ "outcome": "deleted" }
```

Example 3 input, trashing the post of example 1 again:

```json
{ "where": { "type": "post", "id": 123 } }
```

Example 3 error:

```json
{ "code": "content_already_trashed", "message": "The post has already been trashed.", "data": { "status": 410 } }
```

Input schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "where" ],
  "properties": {
    "where": {
      "type": "object",
      "additionalProperties": false,
      "required": [ "type", "id" ],
      "properties": { "type": { "type": "string", "enum": [ "post", "page" ] }, "id": { "type": "integer", "minimum": 1 } }
    },
    "permanent": { "type": "boolean" }
  }
}
```

When this ability is added, `core/content-query` takes `trash` in `where.status`, so trashed posts can be read.

Output schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "outcome" ],
  "properties": { "outcome": { "type": "string" } }
}
```

## Settings abilities

### `core/settings-get`

Example 1 input, one group:

```json
{ "group": "reading" }
```

Example 1 output:

```json
{ "posts_per_page": 10, "show_on_front": "posts", "page_on_front": 0, "page_for_posts": 0, "page_for_privacy_policy": 3 }
```

Example 2 input, two named settings:

```json
{ "fields": [ "title", "timezone" ] }
```

Example 2 output:

```json
{ "title": "Acme Robotics", "timezone": "Europe/Lisbon" }
```

Input schema:

```json
{
  "type": "object",
  "default": {},
  "additionalProperties": false,
  "properties": {
    "group":  { "type": "string", "enum": [ "general", "writing", "reading", "discussion" ] },
    "fields": {
      "type": "array",
      "uniqueItems": true,
      "items": {
        "type": "string",
        "enum": [ "title", "description", "url", "email", "timezone", "date_format", "time_format", "start_of_week", "language", "use_smilies", "default_category", "default_post_format", "posts_per_page", "show_on_front", "page_on_front", "page_for_posts", "page_for_privacy_policy", "default_ping_status", "default_comment_status" ]
      }
    }
  }
}
```

Output schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "properties": {
    "title":                   { "type": "string" },
    "description":             { "type": "string" },
    "url":                     { "type": "string", "format": "uri" },
    "email":                   { "type": "string", "format": "email" },
    "timezone":                { "type": "string" },
    "date_format":             { "type": "string" },
    "time_format":             { "type": "string" },
    "start_of_week":           { "type": "integer" },
    "language":                { "type": "string" },
    "use_smilies":             { "type": "boolean" },
    "default_category":        { "type": "integer" },
    "default_post_format":     { "type": "string" },
    "posts_per_page":          { "type": "integer" },
    "show_on_front":           { "type": "string" },
    "page_on_front":           { "type": "integer" },
    "page_for_posts":          { "type": "integer" },
    "page_for_privacy_policy": { "type": "integer" },
    "default_ping_status":     { "type": "string", "enum": [ "open", "closed" ] },
    "default_comment_status":  { "type": "string", "enum": [ "open", "closed" ] }
  }
}
```

### `core/settings-update`

Example 1 input, a title, a reading option and a deleted option:

```json
{ "data": { "title": "Acme Robotics", "posts_per_page": 12, "use_smilies": null } }
```

Over REST:

```http
POST /wp-json/wp-abilities/v1/abilities/core/settings-update/run
{ "input": { "data": { "title": "Acme Robotics", "posts_per_page": 12, "use_smilies": null } } }
```

Example 1 output:

```json
{ "title": "Acme Robotics", "posts_per_page": 12, "use_smilies": true }
```

Example 2 input, closing discussion on new posts:

```json
{ "data": { "default_comment_status": "closed", "default_ping_status": "closed" } }
```

Example 2 output:

```json
{ "default_comment_status": "closed", "default_ping_status": "closed" }
```

Example 3 input, deleting a setting whose stored value no longer matches its schema:

```json
{ "data": { "posts_per_page": null } }
```

Example 3 error:

```json
{
  "code": "settings_invalid_stored_value",
  "message": "The posts_per_page property has an invalid stored value, and cannot be updated to null.",
  "data": { "status": 500 }
}
```

Input schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "data" ],
  "properties": {
    "data": {
      "type": "object",
      "additionalProperties": false,
      "minProperties": 1,
      "properties": {
        "title":                   { "type": [ "string", "null" ] },
        "description":             { "type": [ "string", "null" ] },
        "url":                     { "type": [ "string", "null" ], "format": "uri" },
        "email":                   { "type": [ "string", "null" ], "format": "email" },
        "timezone":                { "type": [ "string", "null" ] },
        "date_format":             { "type": [ "string", "null" ] },
        "time_format":             { "type": [ "string", "null" ] },
        "start_of_week":           { "type": [ "integer", "null" ] },
        "language":                { "type": [ "string", "null" ] },
        "use_smilies":             { "type": [ "boolean", "null" ] },
        "default_category":        { "type": [ "integer", "null" ] },
        "default_post_format":     { "type": [ "string", "null" ] },
        "posts_per_page":          { "type": [ "integer", "null" ] },
        "show_on_front":           { "type": [ "string", "null" ] },
        "page_on_front":           { "type": [ "integer", "null" ] },
        "page_for_posts":          { "type": [ "integer", "null" ] },
        "page_for_privacy_policy": { "type": [ "integer", "null" ] },
        "default_ping_status":     { "type": [ "string", "null" ], "enum": [ "open", "closed", null ] },
        "default_comment_status":  { "type": [ "string", "null" ], "enum": [ "open", "closed", null ] }
      }
    }
  }
}
```

Output schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "properties": {
    "title":                   { "type": "string" },
    "description":             { "type": "string" },
    "url":                     { "type": "string", "format": "uri" },
    "email":                   { "type": "string", "format": "email" },
    "timezone":                { "type": "string" },
    "date_format":             { "type": "string" },
    "time_format":             { "type": "string" },
    "start_of_week":           { "type": "integer" },
    "language":                { "type": "string" },
    "use_smilies":             { "type": "boolean" },
    "default_category":        { "type": "integer" },
    "default_post_format":     { "type": "string" },
    "posts_per_page":          { "type": "integer" },
    "show_on_front":           { "type": "string" },
    "page_on_front":           { "type": "integer" },
    "page_for_posts":          { "type": "integer" },
    "page_for_privacy_policy": { "type": "integer" },
    "default_ping_status":     { "type": "string", "enum": [ "open", "closed" ] },
    "default_comment_status":  { "type": "string", "enum": [ "open", "closed" ] }
  }
}
```

## User abilities

### `core/users-query`

Example 1 input, the editors with chosen fields:

```json
{ "where": { "roles": [ "editor" ] }, "fields": [ "name", "slug", "roles" ] }
```

Example 1 output:

```json
{
  "items": [ { "id": 42, "name": "Maya Carter", "slug": "mcarter", "roles": [ "editor" ] } ],
  "pagination": { "total": 1, "total_pages": 1 }
}
```

Example 2 input, the public authors with the default fields:

```json
{ "where": { "has_published_posts": true } }
```

Example 2 output:

```json
{
  "items": [
    {
      "id": 42,
      "name": "Maya Carter",
      "link": "https://example.test/author/mcarter/",
      "slug": "mcarter",
      "avatar_urls": {
        "24": "https://secure.gravatar.com/avatar/9f3a…?s=24&d=mm&r=g",
        "48": "https://secure.gravatar.com/avatar/9f3a…?s=48&d=mm&r=g",
        "96": "https://secure.gravatar.com/avatar/9f3a…?s=96&d=mm&r=g"
      }
    }
  ],
  "pagination": { "total": 1, "total_pages": 1 }
}
```

Example 3 input, a page past the last one:

```json
{ "where": { "roles": [ "editor" ] }, "pagination": { "page": 2 } }
```

Example 3 error:

```json
{ "code": "users_invalid_page_number", "message": "The page number requested is larger than the number of pages available.", "data": { "status": 404 } }
```

Input schema:

```json
{
  "type": "object",
  "default": {},
  "additionalProperties": false,
  "properties": {
    "where": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "ids":       { "type": "array", "uniqueItems": true, "minItems": 1, "maxItems": 100, "items": { "type": "integer", "minimum": 1 } },
        "slugs":     { "type": "array", "uniqueItems": true, "minItems": 1, "maxItems": 100, "items": { "type": "string", "minLength": 1 } },
        "usernames": { "type": "array", "uniqueItems": true, "minItems": 1, "maxItems": 100, "items": { "type": "string", "minLength": 1 } },
        "roles":     { "type": "array", "uniqueItems": true, "minItems": 1, "items": { "type": "string", "enum": [ "administrator", "editor", "author", "contributor", "subscriber" ] } },
        "has_published_posts": {
          "anyOf": [
            { "type": "boolean", "enum": [ true ] },
            { "type": "array", "uniqueItems": true, "minItems": 1, "items": { "type": "string", "enum": [ "post", "page", "attachment" ] } }
          ]
        }
      }
    },
    "fields": {
      "type": "array",
      "uniqueItems": true,
      "items": {
        "type": "string",
        "enum": [ "id", "name", "description", "url", "link", "slug", "avatar_urls", "username", "email", "first_name", "last_name", "nickname", "locale", "registered", "registered.site", "registered.gmt", "roles" ]
      }
    },
    "pagination": {
      "type": "object",
      "additionalProperties": false,
      "properties": { "page": { "type": "integer", "minimum": 1 }, "per_page": { "type": "integer", "minimum": 1, "maximum": 100 } }
    }
  }
}
```

Default `fields`: `id, name, link, slug, avatar_urls`.

Output schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "items", "pagination" ],
  "properties": {
    "items": {
      "type": "array",
      "items": {
        "type": "object",
        "additionalProperties": false,
        "required": [ "id" ],
        "properties": {
          "id":              { "type": "integer" },
          "name":            { "type": "string" },
          "description":     { "type": "string" },
          "url":             { "type": "string" },
          "link":            { "type": "string", "format": "uri" },
          "slug":            { "type": "string" },
          "avatar_urls":     { "type": "object", "additionalProperties": { "type": [ "string", "null" ], "format": "uri" } },
          "username":        { "type": "string" },
          "email":           { "type": [ "string", "null" ], "format": "email" },
          "first_name":      { "type": "string" },
          "last_name":       { "type": "string" },
          "nickname":        { "type": "string" },
          "locale":          { "type": "string" },
          "registered": {
            "type": "object",
            "additionalProperties": false,
            "properties": { "site": { "type": [ "string", "null" ], "format": "date-time" }, "gmt": { "type": [ "string", "null" ], "format": "date-time" } }
          },
          "roles":           { "type": "array", "items": { "type": "string" } }
        }
      }
    },
    "pagination": {
      "type": "object",
      "additionalProperties": false,
      "required": [ "total", "total_pages" ],
      "properties": { "total": { "type": "integer" }, "total_pages": { "type": "integer" } }
    }
  }
}
```

### `core/user-create`

Example 1 input, an editor with chosen fields:

```json
{
  "data": { "username": "mcarter", "email": "m.carter@example.test", "name": "Maya Carter", "first_name": "Maya", "last_name": "Carter", "roles": [ "editor" ] },
  "fields": [ "name", "slug", "username", "email", "registered", "roles" ]
}
```

Example 1 output:

```json
{
  "id": 42,
  "name": "Maya Carter",
  "slug": "mcarter",
  "username": "mcarter",
  "email": "m.carter@example.test",
  "registered": { "site": "2026-10-21T09:30:00+01:00", "gmt": "2026-10-21T08:30:00+00:00" },
  "roles": [ "editor" ]
}
```

Example 2 input, the minimum input with the default fields:

```json
{ "data": { "username": "jdoe", "email": "j.doe@example.test" } }
```

Example 2 output:

```json
{
  "id": 43,
  "name": "jdoe",
  "link": "https://example.test/author/jdoe/",
  "slug": "jdoe",
  "avatar_urls": {
    "24": "https://secure.gravatar.com/avatar/5a1d…?s=24&d=mm&r=g",
    "48": "https://secure.gravatar.com/avatar/5a1d…?s=48&d=mm&r=g",
    "96": "https://secure.gravatar.com/avatar/5a1d…?s=96&d=mm&r=g"
  }
}
```

Example 3 input, a username that is taken:

```json
{ "data": { "username": "mcarter", "email": "maya.c@example.test" } }
```

Example 3 error:

```json
{
  "code": "user_exists",
  "message": "Sorry, that username already exists!",
  "data": { "status": 400, "params": { "data[username]": "Sorry, that username already exists!" } }
}
```

Input schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "data" ],
  "properties": {
    "data": {
      "type": "object",
      "additionalProperties": false,
      "required": [ "username", "email" ],
      "properties": {
        "username":    { "type": "string", "minLength": 1 },
        "email":       { "type": "string", "format": "email" },
        "name":        { "type": "string", "minLength": 1 },
        "first_name":  { "type": "string" },
        "last_name":   { "type": "string" },
        "nickname":    { "type": "string", "minLength": 1 },
        "slug":        { "type": "string", "minLength": 1 },
        "description": { "type": "string" },
        "url":         { "type": "string" },
        "locale":      { "type": "string", "enum": [ "", "en_US", "pt_PT" ] },
        "roles":       { "type": "array", "uniqueItems": true, "minItems": 1, "items": { "type": "string", "enum": [ "administrator", "editor", "author", "contributor", "subscriber" ] } }
      }
    },
    "fields": {
      "type": "array",
      "uniqueItems": true,
      "items": {
        "type": "string",
        "enum": [ "id", "name", "description", "url", "link", "slug", "avatar_urls", "username", "email", "first_name", "last_name", "nickname", "locale", "registered", "registered.site", "registered.gmt", "roles" ]
      }
    }
  }
}
```

Default `fields`: `id, name, link, slug, avatar_urls`.

The input takes no `password`: WordPress generates one and emails the new user a link to set their own, the same email the Add User screen sends when "Send the new user an email about their account" is checked. The site admin gets the usual new user notice.

Output schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "id" ],
  "properties": {
    "id":              { "type": "integer" },
    "name":            { "type": "string" },
    "description":     { "type": "string" },
    "url":             { "type": "string" },
    "link":            { "type": "string", "format": "uri" },
    "slug":            { "type": "string" },
    "avatar_urls":     { "type": "object", "additionalProperties": { "type": [ "string", "null" ], "format": "uri" } },
    "username":        { "type": "string" },
    "email":           { "type": [ "string", "null" ], "format": "email" },
    "first_name":      { "type": "string" },
    "last_name":       { "type": "string" },
    "nickname":        { "type": "string" },
    "locale":          { "type": "string" },
    "registered": {
      "type": "object",
      "additionalProperties": false,
      "properties": { "site": { "type": [ "string", "null" ], "format": "date-time" }, "gmt": { "type": [ "string", "null" ], "format": "date-time" } }
    },
    "roles":           { "type": "array", "items": { "type": "string" } }
  }
}
```

### `core/user-update`

Example 1 input, a role change and a biography:

```json
{
  "where": { "id": 42 },
  "data": { "roles": [ "author" ], "description": "Writes about hardware." },
  "fields": [ "name", "description", "roles" ]
}
```

Example 1 output:

```json
{ "id": 42, "name": "Maya Carter", "description": "Writes about hardware.", "roles": [ "author" ] }
```

Example 2 input, a user changing their own email address:

```json
{ "where": { "id": 42 }, "data": { "email": "maya@example.test" }, "fields": [ "email" ] }
```

Example 2 output:

```json
{ "id": 42, "email": "maya@example.test" }
```

Example 3 input, an editor trying to change roles, which needs `promote_user`:

```json
{ "where": { "id": 43 }, "data": { "roles": [ "author" ] } }
```

Example 3 error:

```json
{ "code": "user_cannot_edit_roles", "message": "Sorry, you are not allowed to edit the roles of this user.", "data": { "status": 403 } }
```

Input schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "where", "data" ],
  "properties": {
    "where": { "type": "object", "additionalProperties": false, "required": [ "id" ], "properties": { "id": { "type": "integer", "minimum": 1 } } },
    "data": {
      "type": "object",
      "additionalProperties": false,
      "minProperties": 1,
      "properties": {
        "email":       { "type": "string", "format": "email" },
        "name":        { "type": "string", "minLength": 1 },
        "first_name":  { "type": "string" },
        "last_name":   { "type": "string" },
        "nickname":    { "type": "string", "minLength": 1 },
        "slug":        { "type": "string", "minLength": 1 },
        "description": { "type": "string" },
        "url":         { "type": "string" },
        "locale":      { "type": "string", "enum": [ "", "en_US", "pt_PT" ] },
        "roles":       { "type": "array", "uniqueItems": true, "minItems": 1, "items": { "type": "string", "enum": [ "administrator", "editor", "author", "contributor", "subscriber" ] } }
      }
    },
    "fields": {
      "type": "array",
      "uniqueItems": true,
      "items": {
        "type": "string",
        "enum": [ "id", "name", "description", "url", "link", "slug", "avatar_urls", "username", "email", "first_name", "last_name", "nickname", "locale", "registered", "registered.site", "registered.gmt", "roles" ]
      }
    }
  }
}
```

Default `fields`: `id, name, link, slug, avatar_urls`.

Output schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "id" ],
  "properties": {
    "id":              { "type": "integer" },
    "name":            { "type": "string" },
    "description":     { "type": "string" },
    "url":             { "type": "string" },
    "link":            { "type": "string", "format": "uri" },
    "slug":            { "type": "string" },
    "avatar_urls":     { "type": "object", "additionalProperties": { "type": [ "string", "null" ], "format": "uri" } },
    "username":        { "type": "string" },
    "email":           { "type": [ "string", "null" ], "format": "email" },
    "first_name":      { "type": "string" },
    "last_name":       { "type": "string" },
    "nickname":        { "type": "string" },
    "locale":          { "type": "string" },
    "registered": {
      "type": "object",
      "additionalProperties": false,
      "properties": { "site": { "type": [ "string", "null" ], "format": "date-time" }, "gmt": { "type": [ "string", "null" ], "format": "date-time" } }
    },
    "roles":           { "type": "array", "items": { "type": "string" } }
  }
}
```

### `core/user-delete`

Example 1 input, deleting a user and handing their posts to user 7:

```json
{ "where": { "id": 42 }, "reassign": 7 }
```

Over REST:

```http
DELETE /wp-json/wp-abilities/v1/abilities/core/user-delete/run?input[where][id]=42&input[reassign]=7
```

Example 1 output:

```json
{ "outcome": "deleted" }
```

Example 2 output, the same call on a multisite network:

```json
{ "outcome": "removed" }
```

Input schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "where", "reassign" ],
  "properties": {
    "where":    { "type": "object", "additionalProperties": false, "required": [ "id" ], "properties": { "id": { "type": "integer", "minimum": 1 } } },
    "reassign": { "type": [ "integer", "boolean" ], "minimum": 1 }
  }
}
```

`reassign` is required: the ID of the user who takes over the deleted user's content, or `false` to reassign nothing. With `false` the user's content is removed as well, as "Delete all content" does on the Users screen (posts and pages go to the trash); send it too when the user has no content. `true` is rejected.

On multisite the user is removed from the current site instead, as "Remove" does on the site's Users screen: `outcome` is `removed` and the account stays in the network. There, `reassign: false` leaves the user's content in place; a user ID still reassigns it.

Output schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "outcome" ],
  "properties": { "outcome": { "type": "string" } }
}
```

## Media abilities

### `core/media-query`

Example 1 input, the images attached to post 123 with chosen fields:

```json
{
  "where": { "media_type": [ "image" ], "post": 123 },
  "fields": [ "title.rendered", "alt_text", "source_url", "media_details.width", "media_details.height" ]
}
```

Example 1 output:

```json
{
  "items": [
    {
      "id": 310,
      "title": { "rendered": "Team photo" },
      "alt_text": "The team on the office roof",
      "source_url": "https://example.test/wp-content/uploads/2026/10/team-photo.jpg",
      "media_details": { "width": 2048, "height": 1365 }
    }
  ],
  "pagination": { "total": 1, "total_pages": 1 }
}
```

Example 2 input, two attachments by ID with the default fields:

```json
{ "where": { "ids": [ 310, 311 ] } }
```

Example 2 output:

```json
{
  "items": [
    {
      "id": 310,
      "status": "inherit",
      "date": { "site": "2026-10-21T09:30:00+01:00", "gmt": "2026-10-21T08:30:00+00:00" },
      "slug": "team-photo",
      "title": { "rendered": "Team photo" },
      "media_type": "image",
      "mime_type": "image/jpeg",
      "source_url": "https://example.test/wp-content/uploads/2026/10/team-photo.jpg"
    },
    {
      "id": 311,
      "status": "inherit",
      "date": { "site": "2026-10-20T16:00:00+01:00", "gmt": "2026-10-20T15:00:00+00:00" },
      "slug": "press-kit",
      "title": { "rendered": "Press kit" },
      "media_type": "file",
      "mime_type": "application/pdf",
      "source_url": "https://example.test/wp-content/uploads/2026/10/press-kit.pdf"
    }
  ],
  "pagination": { "total": 2, "total_pages": 1 }
}
```

Example 3 input, a subscriber asking for trashed attachments:

```json
{ "where": { "status": [ "trash" ] } }
```

Example 3 error:

```json
{ "code": "media_cannot_filter_by_status", "message": "Sorry, you are not allowed to filter media by this status.", "data": { "status": 403 } }
```

Input schema:

```json
{
  "type": "object",
  "default": {},
  "additionalProperties": false,
  "properties": {
    "where": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "ids":         { "type": "array", "uniqueItems": true, "minItems": 1, "maxItems": 100, "items": { "type": "integer", "minimum": 1 } },
        "slugs":       { "type": "array", "uniqueItems": true, "minItems": 1, "maxItems": 100, "items": { "type": "string", "minLength": 1 } },
        "status":      { "type": "array", "uniqueItems": true, "minItems": 1, "items": { "type": "string", "enum": [ "inherit", "private", "trash" ] } },
        "media_type":  { "type": "array", "uniqueItems": true, "minItems": 1, "items": { "type": "string", "enum": [ "application", "audio", "image", "text", "video" ] } },
        "mime_type":   { "type": "array", "uniqueItems": true, "minItems": 1, "items": { "type": "string", "minLength": 1 } },
        "post":        { "type": "integer", "minimum": 0 },
        "author":      {
          "type": "object",
          "additionalProperties": false,
          "minProperties": 1,
          "properties": {
            "id":   { "type": "integer", "minimum": 1 },
            "slug": { "type": "string", "minLength": 1 }
          }
        }
      }
    },
    "fields": {
      "type": "array",
      "uniqueItems": true,
      "items": {
        "type": "string",
        "enum": [
          "id", "status", "date", "date.site", "date.gmt", "modified", "modified.site",
          "modified.gmt", "slug", "link", "title", "title.raw", "title.rendered", "caption",
          "caption.raw", "caption.rendered", "description", "description.raw",
          "description.rendered", "alt_text", "media_type", "mime_type", "source_url", "filename",
          "filesize", "media_details", "media_details.width", "media_details.height",
          "media_details.length", "media_details.sizes", "post", "author", "author.id",
          "author.name", "author.slug"
        ]
      }
    },
    "pagination": {
      "type": "object",
      "additionalProperties": false,
      "properties": { "page": { "type": "integer", "minimum": 1 }, "per_page": { "type": "integer", "minimum": 1, "maximum": 100 } }
    }
  }
}
```

Default `fields`: `id, status, date, slug, title.rendered, media_type, mime_type, source_url`.

Output schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "items", "pagination" ],
  "properties": {
    "items": {
      "type": "array",
      "items": {
        "type": "object",
        "additionalProperties": false,
        "required": [ "id" ],
        "properties": {
          "id":          { "type": "integer" },
          "status":      { "type": "string" },
          "date": {
            "type": "object",
            "additionalProperties": false,
            "properties": { "site": { "type": [ "string", "null" ], "format": "date-time" }, "gmt": { "type": [ "string", "null" ], "format": "date-time" } }
          },
          "modified": {
            "type": "object",
            "additionalProperties": false,
            "properties": { "site": { "type": [ "string", "null" ], "format": "date-time" }, "gmt": { "type": [ "string", "null" ], "format": "date-time" } }
          },
          "slug":        { "type": "string" },
          "link":        { "type": "string", "format": "uri" },
          "title":       { "type": "object", "additionalProperties": false, "properties": { "raw": { "type": "string" }, "rendered": { "type": "string" } } },
          "caption":     { "type": "object", "additionalProperties": false, "properties": { "raw": { "type": "string" }, "rendered": { "type": "string" } } },
          "description": { "type": "object", "additionalProperties": false, "properties": { "raw": { "type": "string" }, "rendered": { "type": "string" } } },
          "alt_text":    { "type": "string" },
          "media_type":  { "type": "string" },
          "mime_type":   { "type": "string" },
          "source_url":  { "type": "string", "format": "uri" },
          "filename":    { "type": "string" },
          "filesize":    { "type": "integer" },
          "media_details": {
            "type": "object",
            "additionalProperties": false,
            "properties": {
              "width":  { "type": "integer" },
              "height": { "type": "integer" },
              "length": { "type": "integer" },
              "sizes": {
                "type": "object",
                "additionalProperties": {
                  "type": "object",
                  "additionalProperties": false,
                  "required": [ "width", "height", "mime_type", "source_url" ],
                  "properties": { "width": { "type": "integer" }, "height": { "type": "integer" }, "mime_type": { "type": "string" }, "source_url": { "type": "string", "format": "uri" } }
                }
              }
            }
          },
          "post":        { "type": "integer" },
          "author": {
            "type": "object",
            "additionalProperties": false,
            "properties": { "id": { "type": "integer" }, "name": { "type": [ "string", "null" ] }, "slug": { "type": [ "string", "null" ] } }
          }
        }
      }
    },
    "pagination": {
      "type": "object",
      "additionalProperties": false,
      "required": [ "total", "total_pages" ],
      "properties": { "total": { "type": "integer" }, "total_pages": { "type": "integer" } }
    }
  }
}
```

### `core/media-upload`

Example 1 input, an image from a URL, attached to post 123:

```json
{
  "data": {
    "file": { "url": "https://cdn.example.test/press/team-photo.jpg" },
    "title": "Team photo",
    "alt_text": "The team on the office roof",
    "post": 123
  },
  "fields": [ "title.rendered", "alt_text", "mime_type", "source_url", "media_details", "post" ]
}
```

Example 1 output:

```json
{
  "id": 310,
  "title": { "rendered": "Team photo" },
  "alt_text": "The team on the office roof",
  "mime_type": "image/jpeg",
  "source_url": "https://example.test/wp-content/uploads/2026/10/team-photo.jpg",
  "media_details": {
    "width": 2048,
    "height": 1365,
    "sizes": {
      "thumbnail": { "width": 150, "height": 150, "mime_type": "image/jpeg", "source_url": "https://example.test/wp-content/uploads/2026/10/team-photo-150x150.jpg" },
      "medium":    { "width": 300, "height": 200, "mime_type": "image/jpeg", "source_url": "https://example.test/wp-content/uploads/2026/10/team-photo-300x200.jpg" },
      "large":     { "width": 1024, "height": 683, "mime_type": "image/jpeg", "source_url": "https://example.test/wp-content/uploads/2026/10/team-photo-1024x683.jpg" }
    }
  },
  "post": 123
}
```

Example 2 input, a PDF from inline bytes with the default fields:

```json
{
  "data": {
    "file": { "base64": "JVBERi0xLjcKJeLjz9MK…", "filename": "press-kit.pdf" },
    "title": "Press kit"
  }
}
```

Example 2 output:

```json
{
  "id": 311,
  "status": "inherit",
  "date": { "site": "2026-10-20T16:00:00+01:00", "gmt": "2026-10-20T15:00:00+00:00" },
  "slug": "press-kit",
  "title": { "rendered": "Press kit" },
  "media_type": "file",
  "mime_type": "application/pdf",
  "source_url": "https://example.test/wp-content/uploads/2026/10/press-kit.pdf"
}
```

Example 3 input, the same PDF given as a URL:

```json
{ "data": { "file": { "url": "https://cdn.example.test/press/press-kit.pdf" } } }
```

Example 3 error:

```json
{
  "code": "media_invalid_file",
  "message": "Only images can be uploaded from a URL.",
  "data": { "status": 400, "params": { "data[file][url]": "Only images can be uploaded from a URL." } }
}
```

Input schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "data" ],
  "properties": {
    "data": {
      "type": "object",
      "additionalProperties": false,
      "required": [ "file" ],
      "properties": {
        "file": {
          "anyOf": [
            { "type": "object", "additionalProperties": false, "required": [ "url" ], "properties": { "url": { "type": "string", "format": "uri" } } },
            {
              "type": "object",
              "additionalProperties": false,
              "required": [ "base64", "filename" ],
              "properties": { "base64": { "type": "string", "minLength": 1 }, "filename": { "type": "string", "minLength": 1 } }
            }
          ]
        },
        "title":       { "type": "string" },
        "caption":     { "type": "string" },
        "description": { "type": "string" },
        "alt_text":    { "type": "string" },
        "slug":        { "type": "string", "minLength": 1 },
        "date": {
          "type": "object",
          "additionalProperties": false,
          "minProperties": 1,
          "properties": { "site": { "type": "string", "format": "date-time" }, "gmt": { "type": "string", "format": "date-time" } }
        },
        "author":      { "type": "integer", "minimum": 1 },
        "post":        { "type": "integer", "minimum": 1 }
      }
    },
    "fields": {
      "type": "array",
      "uniqueItems": true,
      "items": {
        "type": "string",
        "enum": [
          "id", "status", "date", "date.site", "date.gmt", "modified", "modified.site",
          "modified.gmt", "slug", "link", "title", "title.raw", "title.rendered", "caption",
          "caption.raw", "caption.rendered", "description", "description.raw",
          "description.rendered", "alt_text", "media_type", "mime_type", "source_url", "filename",
          "filesize", "media_details", "media_details.width", "media_details.height",
          "media_details.length", "media_details.sizes", "post", "author", "author.id",
          "author.name", "author.slug"
        ]
      }
    }
  }
}
```

Default `fields`: `id, status, date, slug, title.rendered, media_type, mime_type, source_url`.

`data.date` takes `site`, `gmt` or both; `site` wins when both are sent.

Output schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "id" ],
  "properties": {
    "id":          { "type": "integer" },
    "status":      { "type": "string" },
    "date": {
      "type": "object",
      "additionalProperties": false,
      "properties": { "site": { "type": [ "string", "null" ], "format": "date-time" }, "gmt": { "type": [ "string", "null" ], "format": "date-time" } }
    },
    "modified": {
      "type": "object",
      "additionalProperties": false,
      "properties": { "site": { "type": [ "string", "null" ], "format": "date-time" }, "gmt": { "type": [ "string", "null" ], "format": "date-time" } }
    },
    "slug":        { "type": "string" },
    "link":        { "type": "string", "format": "uri" },
    "title":       { "type": "object", "additionalProperties": false, "properties": { "raw": { "type": "string" }, "rendered": { "type": "string" } } },
    "caption":     { "type": "object", "additionalProperties": false, "properties": { "raw": { "type": "string" }, "rendered": { "type": "string" } } },
    "description": { "type": "object", "additionalProperties": false, "properties": { "raw": { "type": "string" }, "rendered": { "type": "string" } } },
    "alt_text":    { "type": "string" },
    "media_type":  { "type": "string" },
    "mime_type":   { "type": "string" },
    "source_url":  { "type": "string", "format": "uri" },
    "filename":    { "type": "string" },
    "filesize":    { "type": "integer" },
    "media_details": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "width":  { "type": "integer" },
        "height": { "type": "integer" },
        "length": { "type": "integer" },
        "sizes": {
          "type": "object",
          "additionalProperties": {
            "type": "object",
            "additionalProperties": false,
            "required": [ "width", "height", "mime_type", "source_url" ],
            "properties": { "width": { "type": "integer" }, "height": { "type": "integer" }, "mime_type": { "type": "string" }, "source_url": { "type": "string", "format": "uri" } }
          }
        }
      }
    },
    "post":        { "type": "integer" },
    "author": {
      "type": "object",
      "additionalProperties": false,
      "properties": { "id": { "type": "integer" }, "name": { "type": [ "string", "null" ] }, "slug": { "type": [ "string", "null" ] } }
    }
  }
}
```

### `core/media-update`

Example 1 input, a caption and a new alt text:

```json
{
  "where": { "id": 310 },
  "data": { "caption": "October 2026", "alt_text": "The team on the office roof, October 2026" },
  "fields": [ "caption", "alt_text" ]
}
```

Example 1 output:

```json
{
  "id": 310,
  "caption": { "raw": "October 2026", "rendered": "<p>October 2026</p>\n" },
  "alt_text": "The team on the office roof, October 2026"
}
```

Example 2 input, detaching the press kit from its post:

```json
{ "where": { "id": 311 }, "data": { "post": 0 }, "fields": [ "post" ] }
```

Example 2 output:

```json
{ "id": 311, "post": 0 }
```

Example 3 input, attaching it to a post the user cannot edit:

```json
{ "where": { "id": 311 }, "data": { "post": 7 } }
```

Example 3 error:

```json
{ "code": "media_cannot_attach", "message": "Sorry, you are not allowed to attach media to this post.", "data": { "status": 403 } }
```

Input schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "where", "data" ],
  "properties": {
    "where": { "type": "object", "additionalProperties": false, "required": [ "id" ], "properties": { "id": { "type": "integer", "minimum": 1 } } },
    "data": {
      "type": "object",
      "additionalProperties": false,
      "minProperties": 1,
      "properties": {
        "title":       { "type": "string" },
        "caption":     { "type": "string" },
        "description": { "type": "string" },
        "alt_text":    { "type": "string" },
        "slug":        { "type": "string", "minLength": 1 },
        "date": {
          "type": "object",
          "additionalProperties": false,
          "minProperties": 1,
          "properties": { "site": { "type": "string", "format": "date-time" }, "gmt": { "type": "string", "format": "date-time" } }
        },
        "author":      { "type": "integer", "minimum": 1 },
        "post":        { "type": "integer", "minimum": 0 }
      }
    },
    "fields": {
      "type": "array",
      "uniqueItems": true,
      "items": {
        "type": "string",
        "enum": [
          "id", "status", "date", "date.site", "date.gmt", "modified", "modified.site",
          "modified.gmt", "slug", "link", "title", "title.raw", "title.rendered", "caption",
          "caption.raw", "caption.rendered", "description", "description.raw",
          "description.rendered", "alt_text", "media_type", "mime_type", "source_url", "filename",
          "filesize", "media_details", "media_details.width", "media_details.height",
          "media_details.length", "media_details.sizes", "post", "author", "author.id",
          "author.name", "author.slug"
        ]
      }
    }
  }
}
```

Default `fields`: `id, status, date, slug, title.rendered, media_type, mime_type, source_url`.

`data.date` takes `site`, `gmt` or both; `site` wins when both are sent.

Output schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "id" ],
  "properties": {
    "id":          { "type": "integer" },
    "status":      { "type": "string" },
    "date": {
      "type": "object",
      "additionalProperties": false,
      "properties": { "site": { "type": [ "string", "null" ], "format": "date-time" }, "gmt": { "type": [ "string", "null" ], "format": "date-time" } }
    },
    "modified": {
      "type": "object",
      "additionalProperties": false,
      "properties": { "site": { "type": [ "string", "null" ], "format": "date-time" }, "gmt": { "type": [ "string", "null" ], "format": "date-time" } }
    },
    "slug":        { "type": "string" },
    "link":        { "type": "string", "format": "uri" },
    "title":       { "type": "object", "additionalProperties": false, "properties": { "raw": { "type": "string" }, "rendered": { "type": "string" } } },
    "caption":     { "type": "object", "additionalProperties": false, "properties": { "raw": { "type": "string" }, "rendered": { "type": "string" } } },
    "description": { "type": "object", "additionalProperties": false, "properties": { "raw": { "type": "string" }, "rendered": { "type": "string" } } },
    "alt_text":    { "type": "string" },
    "media_type":  { "type": "string" },
    "mime_type":   { "type": "string" },
    "source_url":  { "type": "string", "format": "uri" },
    "filename":    { "type": "string" },
    "filesize":    { "type": "integer" },
    "media_details": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "width":  { "type": "integer" },
        "height": { "type": "integer" },
        "length": { "type": "integer" },
        "sizes": {
          "type": "object",
          "additionalProperties": {
            "type": "object",
            "additionalProperties": false,
            "required": [ "width", "height", "mime_type", "source_url" ],
            "properties": { "width": { "type": "integer" }, "height": { "type": "integer" }, "mime_type": { "type": "string" }, "source_url": { "type": "string", "format": "uri" } }
          }
        }
      }
    },
    "post":        { "type": "integer" },
    "author": {
      "type": "object",
      "additionalProperties": false,
      "properties": { "id": { "type": "integer" }, "name": { "type": [ "string", "null" ] }, "slug": { "type": [ "string", "null" ] } }
    }
  }
}
```

### `core/media-delete`

Example 1 input, a permanent deletion:

```json
{ "where": { "id": 310 }, "permanent": true }
```

Over REST:

```http
DELETE /wp-json/wp-abilities/v1/abilities/core/media-delete/run?input[where][id]=310&input[permanent]=true
```

Example 1 output:

```json
{ "outcome": "deleted" }
```

Example 2 input, the same call without `permanent` on a site where the media trash is off:

```json
{ "where": { "id": 310 } }
```

Example 2 error:

```json
{ "code": "media_trash_not_supported", "message": "The attachment does not support trashing. Set \"permanent\" to delete it.", "data": { "status": 501 } }
```

Input schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "where" ],
  "properties": {
    "where":     { "type": "object", "additionalProperties": false, "required": [ "id" ], "properties": { "id": { "type": "integer", "minimum": 1 } } },
    "permanent": { "type": "boolean" }
  }
}
```

When `MEDIA_TRASH` is off, which is the default, `permanent: true` is required: an attachment cannot be trashed there, only deleted.

Output schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "outcome" ],
  "properties": { "outcome": { "type": "string" } }
}
```

## Term abilities

### `core/terms-query`

Example 1 input, the top-level categories, two per page:

```json
{
  "where": { "taxonomy": "category", "parent": 0 },
  "fields": [ "name", "slug", "count" ],
  "pagination": { "per_page": 2 }
}
```

Example 1 output:

```json
{
  "items": [
    { "id": 3, "name": "News", "slug": "news", "count": 12 },
    { "id": 1, "name": "Uncategorized", "slug": "uncategorized", "count": 4 }
  ],
  "pagination": { "total": 5, "total_pages": 3 }
}
```

Example 2 input, the tags of post 123 with the default fields:

```json
{ "where": { "taxonomy": "post_tag", "post": 123 } }
```

Example 2 output:

```json
{
  "items": [
    { "id": 15, "taxonomy": "post_tag", "name": "beta", "slug": "beta", "link": "https://example.test/tag/beta/" }
  ],
  "pagination": { "total": 1, "total_pages": 1 }
}
```

Example 3 input, `parent` on a flat taxonomy:

```json
{ "where": { "taxonomy": "post_tag", "parent": 0 } }
```

Example 3 error:

```json
{
  "code": "terms_invalid_filter",
  "message": "The \"post_tag\" taxonomy is not hierarchical.",
  "data": { "status": 400, "params": { "where[parent]": "The \"post_tag\" taxonomy is not hierarchical." } }
}
```

Input schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "where" ],
  "properties": {
    "where": {
      "type": "object",
      "additionalProperties": false,
      "required": [ "taxonomy" ],
      "properties": {
        "taxonomy":   { "type": "string", "enum": [ "category", "post_tag" ] },
        "ids":        { "type": "array", "uniqueItems": true, "minItems": 1, "maxItems": 100, "items": { "type": "integer", "minimum": 1 } },
        "slugs":      { "type": "array", "uniqueItems": true, "minItems": 1, "maxItems": 100, "items": { "type": "string", "minLength": 1 } },
        "parent":     { "type": "integer", "minimum": 0 },
        "post":       { "type": "integer", "minimum": 1 },
        "hide_empty": { "type": "boolean" }
      }
    },
    "fields": { "type": "array", "uniqueItems": true, "items": { "type": "string", "enum": [ "id", "taxonomy", "name", "slug", "description", "parent", "count", "link" ] } },
    "pagination": {
      "type": "object",
      "additionalProperties": false,
      "properties": { "page": { "type": "integer", "minimum": 1 }, "per_page": { "type": "integer", "minimum": 1, "maximum": 100 } }
    }
  }
}
```

Default `fields`: `id, taxonomy, name, slug, link`.

Output schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "items", "pagination" ],
  "properties": {
    "items": {
      "type": "array",
      "items": {
        "type": "object",
        "additionalProperties": false,
        "required": [ "id" ],
        "properties": {
          "id":          { "type": "integer" },
          "taxonomy":    { "type": "string" },
          "name":        { "type": "string" },
          "slug":        { "type": "string" },
          "description": { "type": "string" },
          "parent":      { "type": "integer" },
          "count":       { "type": "integer" },
          "link":        { "type": "string", "format": "uri" }
        }
      }
    },
    "pagination": {
      "type": "object",
      "additionalProperties": false,
      "required": [ "total", "total_pages" ],
      "properties": { "total": { "type": "integer" }, "total_pages": { "type": "integer" } }
    }
  }
}
```

### `core/term-create`

Example 1 input, a child category under News:

```json
{
  "data": { "taxonomy": "category", "name": "Product updates", "parent": 3 },
  "fields": [ "name", "slug", "parent", "link" ]
}
```

Example 1 output:

```json
{
  "id": 9,
  "name": "Product updates",
  "slug": "product-updates",
  "parent": 3,
  "link": "https://example.test/category/news/product-updates/"
}
```

Example 2 input, a tag with the default fields:

```json
{ "data": { "taxonomy": "post_tag", "name": "beta" } }
```

Example 2 output:

```json
{ "id": 15, "taxonomy": "post_tag", "name": "beta", "slug": "beta", "link": "https://example.test/tag/beta/" }
```

Example 3 error, the first call repeated:

```json
{
  "code": "term_exists",
  "message": "A term with the name provided already exists with this parent.",
  "data": { "status": 400, "term_id": 9 }
}
```

Input schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "data" ],
  "properties": {
    "data": {
      "type": "object",
      "additionalProperties": false,
      "required": [ "taxonomy", "name" ],
      "properties": {
        "taxonomy":    { "type": "string", "enum": [ "category", "post_tag" ] },
        "name":        { "type": "string", "minLength": 1 },
        "slug":        { "type": "string", "minLength": 1 },
        "description": { "type": "string" },
        "parent":      { "type": "integer", "minimum": 0 }
      }
    },
    "fields":   { "type": "array", "uniqueItems": true, "items": { "type": "string", "enum": [ "id", "taxonomy", "name", "slug", "description", "parent", "count", "link" ] } }
  }
}
```

Default `fields`: `id, taxonomy, name, slug, link`.

Output schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "id" ],
  "properties": {
    "id":          { "type": "integer" },
    "taxonomy":    { "type": "string" },
    "name":        { "type": "string" },
    "slug":        { "type": "string" },
    "description": { "type": "string" },
    "parent":      { "type": "integer" },
    "count":       { "type": "integer" },
    "link":        { "type": "string", "format": "uri" }
  }
}
```

### `core/term-update`

Example 1 input, a description:

```json
{
  "where": { "taxonomy": "category", "id": 9 },
  "data": { "description": "Release notes and roadmap updates." },
  "fields": [ "name", "description" ]
}
```

Example 1 output:

```json
{ "id": 9, "name": "Product updates", "description": "Release notes and roadmap updates." }
```

Example 2 input, moving the category to the top level, which changes its link:

```json
{ "where": { "taxonomy": "category", "id": 9 }, "data": { "parent": 0 }, "fields": [ "name", "parent", "link" ] }
```

Example 2 output:

```json
{ "id": 9, "name": "Product updates", "parent": 0, "link": "https://example.test/category/product-updates/" }
```

Example 3 input, a term that is not in the taxonomy named in `where`:

```json
{ "where": { "taxonomy": "post_tag", "id": 9 }, "data": { "name": "Product updates" } }
```

Example 3 error:

```json
{ "code": "term_not_found", "message": "The requested term was not found.", "data": { "status": 404 } }
```

Input schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "where", "data" ],
  "properties": {
    "where": {
      "type": "object",
      "additionalProperties": false,
      "required": [ "taxonomy", "id" ],
      "properties": { "taxonomy": { "type": "string", "enum": [ "category", "post_tag" ] }, "id": { "type": "integer", "minimum": 1 } }
    },
    "data": {
      "type": "object",
      "additionalProperties": false,
      "minProperties": 1,
      "properties": {
        "name":        { "type": "string", "minLength": 1 },
        "slug":        { "type": "string", "minLength": 1 },
        "description": { "type": "string" },
        "parent":      { "type": "integer", "minimum": 0 }
      }
    },
    "fields": { "type": "array", "uniqueItems": true, "items": { "type": "string", "enum": [ "id", "taxonomy", "name", "slug", "description", "parent", "count", "link" ] } }
  }
}
```

Default `fields`: `id, taxonomy, name, slug, link`.

Output schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "id" ],
  "properties": {
    "id":          { "type": "integer" },
    "taxonomy":    { "type": "string" },
    "name":        { "type": "string" },
    "slug":        { "type": "string" },
    "description": { "type": "string" },
    "parent":      { "type": "integer" },
    "count":       { "type": "integer" },
    "link":        { "type": "string", "format": "uri" }
  }
}
```

### `core/term-delete`

Example 1 input, deleting a tag:

```json
{ "where": { "taxonomy": "post_tag", "id": 15 } }
```

Over REST:

```http
DELETE /wp-json/wp-abilities/v1/abilities/core/term-delete/run?input[where][taxonomy]=post_tag&input[where][id]=15
```

Example 1 output:

```json
{ "outcome": "deleted" }
```

Example 2 input, the default category is refused:

```json
{ "where": { "taxonomy": "category", "id": 1 } }
```

Example 2 error:

```json
{ "code": "term_is_default", "message": "The default category cannot be deleted.", "data": { "status": 400 } }
```

Input schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "where" ],
  "properties": {
    "where": {
      "type": "object",
      "additionalProperties": false,
      "required": [ "taxonomy", "id" ],
      "properties": { "taxonomy": { "type": "string", "enum": [ "category", "post_tag" ] }, "id": { "type": "integer", "minimum": 1 } }
    }
  }
}
```

Output schema:

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "outcome" ],
  "properties": { "outcome": { "type": "string" } }
}
```
