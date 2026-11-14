# Core Abilities Schema Quick Reference

This document is the source of truth for the shape of the core abilities: their names, input and output schemas, and behavior. Where the package code differs, the code is updated to match it.

| Ability | Status | Category | `readonly` | `destructive` | `idempotent` | REST method |
|---|---|---|---|---|---|---|
| [`core/content-query`](#corecontent-query) | Landed, [`213ecca2e9`](https://github.com/WordPress/wordpress-develop/commit/213ecca2e96) | content | `true` | `false` | `true` | `GET` |
| [`core/content-create`](#corecontent-create) | Proposed | content | `false` | `false` | `false` | `POST` |
| [`core/content-update`](#corecontent-update) | Proposed | content | `false` | `false` | `true` | `POST` |
| [`core/content-delete`](#corecontent-delete) | Proposed | content | `false` | `true` | `true` | `DELETE` |
| [`core/settings-get`](#coresettings-get) | Landed, [`b6512b1e8c`](https://github.com/WordPress/wordpress-develop/commit/b6512b1e8ca) | site | `true` | `false` | `true` | `GET` |
| [`core/settings-update`](#coresettings-update) | Proposed | site | `false` | `false` | `true` | `POST` |
| [`core/users-query`](#coreusers-query) | Landed, [`39981fd0fe`](https://github.com/WordPress/wordpress-develop/commit/39981fd0fef) | user | `true` | `false` | `true` | `GET` |
| [`core/user-create`](#coreuser-create) | Proposed | user | `false` | `false` | `false` | `POST` |
| [`core/user-update`](#coreuser-update) | Proposed | user | `false` | `false` | `true` | `POST` |
| [`core/user-delete`](#coreuser-delete) | Proposed | user | `false` | `true` | `true` | `DELETE` |
| [`core/media-query`](#coremedia-query) | Proposed | content | `true` | `false` | `true` | `GET` |
| [`core/media-upload`](#coremedia-upload) | Proposed | content | `false` | `false` | `false` | `POST` |
| [`core/media-update`](#coremedia-update) | Proposed | content | `false` | `false` | `true` | `POST` |
| [`core/media-delete`](#coremedia-delete) | Proposed | content | `false` | `true` | `true` | `DELETE` |
| [`core/terms-query`](#coreterms-query) | Proposed | content | `true` | `false` | `true` | `GET` |
| [`core/term-create`](#coreterm-create) | Proposed | content | `false` | `false` | `false` | `POST` |
| [`core/term-update`](#coreterm-update) | Proposed | content | `false` | `false` | `true` | `POST` |
| [`core/term-delete`](#coreterm-delete) | Proposed | content | `false` | `true` | `true` | `DELETE` |

Errors are `WP_Error` objects. The `// Error` part of an example shows one as the REST response body (`code`, `message`, `data`); errors are not validated against the output schema.

## Content abilities

### `core/content-query`

- Default `fields`: `id, type, status, date, slug, title.rendered`.
- Trashed posts cannot be read today: `where.status` has no `trash` value. It gets one when `core/content-delete` is added.

**Example 1:** the drafts of the `post` type with chosen fields

```jsonc
// Input
{ "where": { "type": "post", "status": [ "draft" ] }, "fields": [ "title", "date", "author" ] }

// Output
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

**Example 2:** the child pages of page 7, two per page, with the default fields

```jsonc
// Input
{ "where": { "type": "page", "parent": 7 }, "pagination": { "per_page": 2 } }

// Output
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

**Example 3:** `parent` on a flat type

```jsonc
// Input
{ "where": { "type": "post", "parent": 7 } }

// Error
{
  "code": "content_invalid_filter",
  "message": "The parent filter is only supported for hierarchical post types.",
  "data": {
    "status": 400,
    "params": {
      "where[parent]": "The parent filter is only supported for hierarchical post types."
    }
  }
}
```

<details>
<summary><b>Input schema</b></summary>

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
        "type": { "type": "string", "enum": [ "post", "page" ] },
        "ids": {
          "type": "array",
          "uniqueItems": true,
          "minItems": 1,
          "maxItems": 100,
          "items": { "type": "integer", "minimum": 1 }
        },
        "slugs": {
          "type": "array",
          "uniqueItems": true,
          "minItems": 1,
          "maxItems": 100,
          "items": { "type": "string", "minLength": 1 }
        },
        "status": {
          "type": "array",
          "uniqueItems": true,
          "minItems": 1,
          "items": {
            "type": "string",
            "enum": [ "publish", "future", "draft", "pending", "private" ]
          }
        },
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
      "properties": {
        "page":     { "type": "integer", "minimum": 1 },
        "per_page": { "type": "integer", "minimum": 1, "maximum": 100 }
      }
    }
  }
}
```

</details>

<details>
<summary><b>Output schema</b></summary>

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
          "id":     { "type": "integer" },
          "type":   { "type": "string" },
          "status": { "type": "string" },
          "date": {
            "type": "object",
            "additionalProperties": false,
            "properties": {
              "site": { "type": [ "string", "null" ], "format": "date-time" },
              "gmt":  { "type": [ "string", "null" ], "format": "date-time" }
            }
          },
          "modified": {
            "type": "object",
            "additionalProperties": false,
            "properties": {
              "site": { "type": [ "string", "null" ], "format": "date-time" },
              "gmt":  { "type": [ "string", "null" ], "format": "date-time" }
            }
          },
          "slug": { "type": "string" },
          "link": { "type": "string", "format": "uri" },
          "title": {
            "type": "object",
            "additionalProperties": false,
            "properties": {
              "raw":      { "type": "string" },
              "rendered": { "type": "string" }
            }
          },
          "excerpt": {
            "type": "object",
            "additionalProperties": false,
            "properties": {
              "raw":       { "type": "string" },
              "rendered":  { "type": "string" },
              "protected": { "type": "boolean" }
            }
          },
          "content": {
            "type": "object",
            "additionalProperties": false,
            "properties": {
              "raw":       { "type": "string" },
              "rendered":  { "type": "string" },
              "protected": { "type": "boolean" }
            }
          },
          "author": {
            "type": "object",
            "additionalProperties": false,
            "properties": {
              "id":   { "type": "integer" },
              "name": { "type": [ "string", "null" ] },
              "slug": { "type": [ "string", "null" ] }
            }
          },
          "parent": { "type": "integer" }
        }
      }
    },
    "pagination": {
      "type": "object",
      "additionalProperties": false,
      "required": [ "total", "total_pages" ],
      "properties": {
        "total":       { "type": "integer" },
        "total_pages": { "type": "integer" }
      }
    }
  }
}
```

</details>

### `core/content-create`

- Default `fields`: `id, type, status, date, slug, title.rendered`.
- `data.date` takes `site`, `gmt` or both; `site` wins when both are sent.

**Example 1:** a draft with the default fields

```jsonc
// Input
{ "data": { "type": "post", "title": "Launch notes" } }

// Output
{
  "id": 123,
  "type": "post",
  "status": "draft",
  "date": { "site": "2026-10-21T09:30:00+01:00", "gmt": "2026-10-21T08:30:00+00:00" },
  "slug": "",
  "title": { "rendered": "Launch notes" }
}
```

**Example 2:** a published child page with chosen fields

```jsonc
// Input
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

// Output
{
  "id": 126,
  "status": "publish",
  "link": "https://example.test/products/pricing/",
  "title": { "rendered": "Pricing" },
  "content": { "rendered": "<p>Plans start at 9 euro per month.</p>\n" },
  "parent": 7
}
```

**Example 3:** an attribute the post type does not support

```jsonc
// Input
{ "data": { "type": "post", "title": "Launch notes", "parent": 7 } }

// Error
{
  "code": "content_invalid_attribute",
  "message": "Invalid attribute: parent.",
  "data": { "status": 400, "params": { "data[parent]": "The post type is not hierarchical." } }
}
```

<details>
<summary><b>Input schema</b></summary>

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
        "status": {
          "type": "string",
          "enum": [ "publish", "future", "draft", "pending", "private" ]
        },
        "slug": { "type": "string", "minLength": 1 },
        "date": {
          "type": "object",
          "additionalProperties": false,
          "minProperties": 1,
          "properties": {
            "site": { "type": "string", "format": "date-time" },
            "gmt":  { "type": "string", "format": "date-time" }
          }
        },
        "author": { "type": "integer", "minimum": 1 },
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
    }
  }
}
```

</details>

<details>
<summary><b>Output schema</b></summary>

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "id" ],
  "properties": {
    "id":     { "type": "integer" },
    "type":   { "type": "string" },
    "status": { "type": "string" },
    "date": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "site": { "type": [ "string", "null" ], "format": "date-time" },
        "gmt":  { "type": [ "string", "null" ], "format": "date-time" }
      }
    },
    "modified": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "site": { "type": [ "string", "null" ], "format": "date-time" },
        "gmt":  { "type": [ "string", "null" ], "format": "date-time" }
      }
    },
    "slug": { "type": "string" },
    "link": { "type": "string", "format": "uri" },
    "title": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "raw":      { "type": "string" },
        "rendered": { "type": "string" }
      }
    },
    "excerpt": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "raw":       { "type": "string" },
        "rendered":  { "type": "string" },
        "protected": { "type": "boolean" }
      }
    },
    "content": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "raw":       { "type": "string" },
        "rendered":  { "type": "string" },
        "protected": { "type": "boolean" }
      }
    },
    "author": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "id":   { "type": "integer" },
        "name": { "type": [ "string", "null" ] },
        "slug": { "type": [ "string", "null" ] }
      }
    },
    "parent": { "type": "integer" }
  }
}
```

</details>

### `core/content-update`

- Default `fields`: `id, type, status, date, slug, title.rendered`.
- `data.date` takes `site`, `gmt` or both; `site` wins when both are sent.

**Example 1:** a new title and excerpt

```jsonc
// Input
{
  "where": { "type": "post", "id": 123 },
  "data": { "title": "Launch notes, updated", "excerpt": "What shipped and what is next." },
  "fields": [ "modified", "title", "excerpt" ]
}

// Output
{
  "id": 123,
  "modified": { "site": "2026-10-21T10:05:00+01:00", "gmt": "2026-10-21T09:05:00+00:00" },
  "title": { "raw": "Launch notes, updated", "rendered": "Launch notes, updated" },
  "excerpt": {
    "raw": "What shipped and what is next.",
    "rendered": "<p>What shipped and what is next.</p>\n",
    "protected": false
  }
}
```

**Example 2:** publishing the draft

```jsonc
// Input
{
  "where": { "type": "post", "id": 123 },
  "data": { "status": "publish" },
  "fields": [ "status", "slug", "link" ]
}

// Output
{
  "id": 123,
  "status": "publish",
  "slug": "launch-notes-updated",
  "link": "https://example.test/launch-notes-updated/"
}
```

**Example 3:** a post the user cannot edit

```jsonc
// Input
{ "where": { "type": "post", "id": 123 }, "data": { "title": "Launch notes" } }

// Error
{
  "code": "content_cannot_edit",
  "message": "Sorry, you are not allowed to edit this post.",
  "data": { "status": 403 }
}
```

<details>
<summary><b>Input schema</b></summary>

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
      "properties": {
        "type": { "type": "string", "enum": [ "post", "page" ] },
        "id":   { "type": "integer", "minimum": 1 }
      }
    },
    "data": {
      "type": "object",
      "additionalProperties": false,
      "minProperties": 1,
      "properties": {
        "title":   { "type": "string" },
        "content": { "type": "string" },
        "excerpt": { "type": "string" },
        "status": {
          "type": "string",
          "enum": [ "publish", "future", "draft", "pending", "private" ]
        },
        "slug": { "type": "string", "minLength": 1 },
        "date": {
          "type": "object",
          "additionalProperties": false,
          "minProperties": 1,
          "properties": {
            "site": { "type": "string", "format": "date-time" },
            "gmt":  { "type": "string", "format": "date-time" }
          }
        },
        "author": { "type": "integer", "minimum": 1 },
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
    }
  }
}
```

</details>

<details>
<summary><b>Output schema</b></summary>

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "id" ],
  "properties": {
    "id":     { "type": "integer" },
    "type":   { "type": "string" },
    "status": { "type": "string" },
    "date": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "site": { "type": [ "string", "null" ], "format": "date-time" },
        "gmt":  { "type": [ "string", "null" ], "format": "date-time" }
      }
    },
    "modified": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "site": { "type": [ "string", "null" ], "format": "date-time" },
        "gmt":  { "type": [ "string", "null" ], "format": "date-time" }
      }
    },
    "slug": { "type": "string" },
    "link": { "type": "string", "format": "uri" },
    "title": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "raw":      { "type": "string" },
        "rendered": { "type": "string" }
      }
    },
    "excerpt": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "raw":       { "type": "string" },
        "rendered":  { "type": "string" },
        "protected": { "type": "boolean" }
      }
    },
    "content": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "raw":       { "type": "string" },
        "rendered":  { "type": "string" },
        "protected": { "type": "boolean" }
      }
    },
    "author": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "id":   { "type": "integer" },
        "name": { "type": [ "string", "null" ] },
        "slug": { "type": [ "string", "null" ] }
      }
    },
    "parent": { "type": "integer" }
  }
}
```

</details>

### `core/content-delete`

- When this ability is added, `core/content-query` takes `trash` in `where.status`, so trashed posts can be read.

**Example 1:** trashing a post

```jsonc
// Input
{ "where": { "type": "post", "id": 123 } }

// Over REST
// DELETE /wp-json/wp-abilities/v1/abilities/core/content-delete/run?input[where][type]=post&input[where][id]=123

// Output
{ "outcome": "trashed" }
```

**Example 2:** deleting a page permanently

```jsonc
// Input
{ "where": { "type": "page", "id": 126 }, "permanent": true }

// Output
{ "outcome": "deleted" }
```

**Example 3:** trashing the post of example 1 again

```jsonc
// Input
{ "where": { "type": "post", "id": 123 } }

// Error
{
  "code": "content_already_trashed",
  "message": "The post has already been trashed.",
  "data": { "status": 410 }
}
```

<details>
<summary><b>Input schema</b></summary>

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
      "properties": {
        "type": { "type": "string", "enum": [ "post", "page" ] },
        "id":   { "type": "integer", "minimum": 1 }
      }
    },
    "permanent": { "type": "boolean" }
  }
}
```

</details>

<details>
<summary><b>Output schema</b></summary>

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "outcome" ],
  "properties": { "outcome": { "type": "string" } }
}
```

</details>

## Settings abilities

### `core/settings-get`

**Example 1:** one group

```jsonc
// Input
{ "group": "reading" }

// Output
{
  "posts_per_page": 10,
  "show_on_front": "posts",
  "page_on_front": 0,
  "page_for_posts": 0,
  "page_for_privacy_policy": 3
}
```

**Example 2:** two named settings

```jsonc
// Input
{ "fields": [ "title", "timezone" ] }

// Output
{ "title": "Acme Robotics", "timezone": "Europe/Lisbon" }
```

<details>
<summary><b>Input schema</b></summary>

```json
{
  "type": "object",
  "default": {},
  "additionalProperties": false,
  "properties": {
    "group": { "type": "string", "enum": [ "general", "writing", "reading", "discussion" ] },
    "fields": {
      "type": "array",
      "uniqueItems": true,
      "items": {
        "type": "string",
        "enum": [
          "title", "description", "url", "email", "timezone", "date_format", "time_format",
          "start_of_week", "language", "use_smilies", "default_category", "default_post_format",
          "posts_per_page", "show_on_front", "page_on_front", "page_for_posts",
          "page_for_privacy_policy", "default_ping_status", "default_comment_status"
        ]
      }
    }
  }
}
```

</details>

<details>
<summary><b>Output schema</b></summary>

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

</details>

### `core/settings-update`

**Example 1:** a title, a reading option and a deleted option

```jsonc
// Input
{ "data": { "title": "Acme Robotics", "posts_per_page": 12, "use_smilies": null } }

// Over REST
// POST /wp-json/wp-abilities/v1/abilities/core/settings-update/run
{ "input": { "data": { "title": "Acme Robotics", "posts_per_page": 12, "use_smilies": null } } }

// Output
{ "title": "Acme Robotics", "posts_per_page": 12, "use_smilies": true }
```

**Example 2:** closing discussion on new posts

```jsonc
// Input
{ "data": { "default_comment_status": "closed", "default_ping_status": "closed" } }

// Output
{ "default_comment_status": "closed", "default_ping_status": "closed" }
```

**Example 3:** deleting a setting whose stored value no longer matches its schema

```jsonc
// Input
{ "data": { "posts_per_page": null } }

// Error
{
  "code": "settings_invalid_stored_value",
  "message": "The posts_per_page property has an invalid stored value, and cannot be updated to null.",
  "data": { "status": 500 }
}
```

<details>
<summary><b>Input schema</b></summary>

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
        "title": { "type": [ "string", "null" ] },
        "description": { "type": [ "string", "null" ] },
        "url": { "type": [ "string", "null" ], "format": "uri" },
        "email": { "type": [ "string", "null" ], "format": "email" },
        "timezone": { "type": [ "string", "null" ] },
        "date_format": { "type": [ "string", "null" ] },
        "time_format": { "type": [ "string", "null" ] },
        "start_of_week": { "type": [ "integer", "null" ] },
        "language": { "type": [ "string", "null" ] },
        "use_smilies": { "type": [ "boolean", "null" ] },
        "default_category": { "type": [ "integer", "null" ] },
        "default_post_format": { "type": [ "string", "null" ] },
        "posts_per_page": { "type": [ "integer", "null" ] },
        "show_on_front": { "type": [ "string", "null" ] },
        "page_on_front": { "type": [ "integer", "null" ] },
        "page_for_posts": { "type": [ "integer", "null" ] },
        "page_for_privacy_policy": { "type": [ "integer", "null" ] },
        "default_ping_status": { "type": [ "string", "null" ], "enum": [ "open", "closed", null ] },
        "default_comment_status": {
          "type": [ "string", "null" ],
          "enum": [ "open", "closed", null ]
        }
      }
    }
  }
}
```

</details>

<details>
<summary><b>Output schema</b></summary>

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

</details>

## User abilities

### `core/users-query`

- Default `fields`: `id, name, link, slug, avatar_urls`.

**Example 1:** the editors with chosen fields

```jsonc
// Input
{ "where": { "roles": [ "editor" ] }, "fields": [ "name", "slug", "roles" ] }

// Output
{
  "items": [ { "id": 42, "name": "Maya Carter", "slug": "mcarter", "roles": [ "editor" ] } ],
  "pagination": { "total": 1, "total_pages": 1 }
}
```

**Example 2:** the public authors with the default fields

```jsonc
// Input
{ "where": { "has_published_posts": true } }

// Output
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

**Example 3:** a page past the last one

```jsonc
// Input
{ "where": { "roles": [ "editor" ] }, "pagination": { "page": 2 } }

// Error
{
  "code": "users_invalid_page_number",
  "message": "The page number requested is larger than the number of pages available.",
  "data": { "status": 404 }
}
```

<details>
<summary><b>Input schema</b></summary>

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
        "ids": {
          "type": "array",
          "uniqueItems": true,
          "minItems": 1,
          "maxItems": 100,
          "items": { "type": "integer", "minimum": 1 }
        },
        "slugs": {
          "type": "array",
          "uniqueItems": true,
          "minItems": 1,
          "maxItems": 100,
          "items": { "type": "string", "minLength": 1 }
        },
        "usernames": {
          "type": "array",
          "uniqueItems": true,
          "minItems": 1,
          "maxItems": 100,
          "items": { "type": "string", "minLength": 1 }
        },
        "roles": {
          "type": "array",
          "uniqueItems": true,
          "minItems": 1,
          "items": {
            "type": "string",
            "enum": [ "administrator", "editor", "author", "contributor", "subscriber" ]
          }
        },
        "has_published_posts": {
          "anyOf": [
            { "type": "boolean", "enum": [ true ] },
            {
              "type": "array",
              "uniqueItems": true,
              "minItems": 1,
              "items": { "type": "string", "enum": [ "post", "page", "attachment" ] }
            }
          ]
        }
      }
    },
    "fields": {
      "type": "array",
      "uniqueItems": true,
      "items": {
        "type": "string",
        "enum": [
          "id", "name", "description", "url", "link", "slug", "avatar_urls", "username", "email",
          "first_name", "last_name", "nickname", "locale", "registered", "registered.site",
          "registered.gmt", "roles"
        ]
      }
    },
    "pagination": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "page":     { "type": "integer", "minimum": 1 },
        "per_page": { "type": "integer", "minimum": 1, "maximum": 100 }
      }
    }
  }
}
```

</details>

<details>
<summary><b>Output schema</b></summary>

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
          "name":        { "type": "string" },
          "description": { "type": "string" },
          "url":         { "type": "string" },
          "link":        { "type": "string", "format": "uri" },
          "slug":        { "type": "string" },
          "avatar_urls": {
            "type": "object",
            "additionalProperties": { "type": [ "string", "null" ], "format": "uri" }
          },
          "username":   { "type": "string" },
          "email":      { "type": [ "string", "null" ], "format": "email" },
          "first_name": { "type": "string" },
          "last_name":  { "type": "string" },
          "nickname":   { "type": "string" },
          "locale":     { "type": "string" },
          "registered": {
            "type": "object",
            "additionalProperties": false,
            "properties": {
              "site": { "type": [ "string", "null" ], "format": "date-time" },
              "gmt":  { "type": [ "string", "null" ], "format": "date-time" }
            }
          },
          "roles": { "type": "array", "items": { "type": "string" } }
        }
      }
    },
    "pagination": {
      "type": "object",
      "additionalProperties": false,
      "required": [ "total", "total_pages" ],
      "properties": {
        "total":       { "type": "integer" },
        "total_pages": { "type": "integer" }
      }
    }
  }
}
```

</details>

### `core/user-create`

- Default `fields`: `id, name, link, slug, avatar_urls`.
- The input takes no `password`: WordPress generates one and emails the new user a link to set their own, the same email the Add User screen sends when "Send the new user an email about their account" is checked. The site admin gets the usual new user notice.

**Example 1:** an editor with chosen fields

```jsonc
// Input
{
  "data": {
    "username": "mcarter",
    "email": "m.carter@example.test",
    "name": "Maya Carter",
    "first_name": "Maya",
    "last_name": "Carter",
    "roles": [ "editor" ]
  },
  "fields": [ "name", "slug", "username", "email", "registered", "roles" ]
}

// Output
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

**Example 2:** the minimum input with the default fields

```jsonc
// Input
{ "data": { "username": "jdoe", "email": "j.doe@example.test" } }

// Output
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

**Example 3:** a username that is taken

```jsonc
// Input
{ "data": { "username": "mcarter", "email": "maya.c@example.test" } }

// Error
{
  "code": "user_exists",
  "message": "Sorry, that username already exists!",
  "data": { "status": 400, "params": { "data[username]": "Sorry, that username already exists!" } }
}
```

<details>
<summary><b>Input schema</b></summary>

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
        "roles": {
          "type": "array",
          "uniqueItems": true,
          "minItems": 1,
          "items": {
            "type": "string",
            "enum": [ "administrator", "editor", "author", "contributor", "subscriber" ]
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
          "id", "name", "description", "url", "link", "slug", "avatar_urls", "username", "email",
          "first_name", "last_name", "nickname", "locale", "registered", "registered.site",
          "registered.gmt", "roles"
        ]
      }
    }
  }
}
```

</details>

<details>
<summary><b>Output schema</b></summary>

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "id" ],
  "properties": {
    "id":          { "type": "integer" },
    "name":        { "type": "string" },
    "description": { "type": "string" },
    "url":         { "type": "string" },
    "link":        { "type": "string", "format": "uri" },
    "slug":        { "type": "string" },
    "avatar_urls": {
      "type": "object",
      "additionalProperties": { "type": [ "string", "null" ], "format": "uri" }
    },
    "username":   { "type": "string" },
    "email":      { "type": [ "string", "null" ], "format": "email" },
    "first_name": { "type": "string" },
    "last_name":  { "type": "string" },
    "nickname":   { "type": "string" },
    "locale":     { "type": "string" },
    "registered": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "site": { "type": [ "string", "null" ], "format": "date-time" },
        "gmt":  { "type": [ "string", "null" ], "format": "date-time" }
      }
    },
    "roles": { "type": "array", "items": { "type": "string" } }
  }
}
```

</details>

### `core/user-update`

- Default `fields`: `id, name, link, slug, avatar_urls`.

**Example 1:** a role change and a biography

```jsonc
// Input
{
  "where": { "id": 42 },
  "data": { "roles": [ "author" ], "description": "Writes about hardware." },
  "fields": [ "name", "description", "roles" ]
}

// Output
{ "id": 42, "name": "Maya Carter", "description": "Writes about hardware.", "roles": [ "author" ] }
```

**Example 2:** a user changing their own email address

```jsonc
// Input
{ "where": { "id": 42 }, "data": { "email": "maya@example.test" }, "fields": [ "email" ] }

// Output
{ "id": 42, "email": "maya@example.test" }
```

**Example 3:** an editor trying to change roles, which needs `promote_user`

```jsonc
// Input
{ "where": { "id": 43 }, "data": { "roles": [ "author" ] } }

// Error
{
  "code": "user_cannot_edit_roles",
  "message": "Sorry, you are not allowed to edit the roles of this user.",
  "data": { "status": 403 }
}
```

<details>
<summary><b>Input schema</b></summary>

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "where", "data" ],
  "properties": {
    "where": {
      "type": "object",
      "additionalProperties": false,
      "required": [ "id" ],
      "properties": { "id": { "type": "integer", "minimum": 1 } }
    },
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
        "roles": {
          "type": "array",
          "uniqueItems": true,
          "minItems": 1,
          "items": {
            "type": "string",
            "enum": [ "administrator", "editor", "author", "contributor", "subscriber" ]
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
          "id", "name", "description", "url", "link", "slug", "avatar_urls", "username", "email",
          "first_name", "last_name", "nickname", "locale", "registered", "registered.site",
          "registered.gmt", "roles"
        ]
      }
    }
  }
}
```

</details>

<details>
<summary><b>Output schema</b></summary>

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "id" ],
  "properties": {
    "id":          { "type": "integer" },
    "name":        { "type": "string" },
    "description": { "type": "string" },
    "url":         { "type": "string" },
    "link":        { "type": "string", "format": "uri" },
    "slug":        { "type": "string" },
    "avatar_urls": {
      "type": "object",
      "additionalProperties": { "type": [ "string", "null" ], "format": "uri" }
    },
    "username":   { "type": "string" },
    "email":      { "type": [ "string", "null" ], "format": "email" },
    "first_name": { "type": "string" },
    "last_name":  { "type": "string" },
    "nickname":   { "type": "string" },
    "locale":     { "type": "string" },
    "registered": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "site": { "type": [ "string", "null" ], "format": "date-time" },
        "gmt":  { "type": [ "string", "null" ], "format": "date-time" }
      }
    },
    "roles": { "type": "array", "items": { "type": "string" } }
  }
}
```

</details>

### `core/user-delete`

- `reassign` is required: the ID of the user who takes over the deleted user's content, or `false` to reassign nothing. With `false` the user's content is removed as well, as "Delete all content" does on the Users screen (posts and pages go to the trash); send it too when the user has no content. `true` is rejected.
- On multisite the user is removed from the current site instead, as "Remove" does on the site's Users screen: `outcome` is `removed` and the account stays in the network. There, `reassign: false` leaves the user's content in place; a user ID still reassigns it.

**Example 1:** deleting a user and handing their posts to user 7

```jsonc
// Input
{ "where": { "id": 42 }, "reassign": 7 }

// Over REST
// DELETE /wp-json/wp-abilities/v1/abilities/core/user-delete/run?input[where][id]=42&input[reassign]=7

// Output
{ "outcome": "deleted" }
```

**Example 2:** the same call on a multisite network

```jsonc
// Output
{ "outcome": "removed" }
```

<details>
<summary><b>Input schema</b></summary>

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "where", "reassign" ],
  "properties": {
    "where": {
      "type": "object",
      "additionalProperties": false,
      "required": [ "id" ],
      "properties": { "id": { "type": "integer", "minimum": 1 } }
    },
    "reassign": { "type": [ "integer", "boolean" ], "minimum": 1 }
  }
}
```

</details>

<details>
<summary><b>Output schema</b></summary>

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "outcome" ],
  "properties": { "outcome": { "type": "string" } }
}
```

</details>

## Media abilities

### `core/media-query`

- Default `fields`: `id, status, date, slug, title.rendered, media_type, mime_type, source_url`.

**Example 1:** the images attached to post 123 with chosen fields

```jsonc
// Input
{
  "where": { "media_type": [ "image" ], "post": 123 },
  "fields": [
    "title.rendered", "alt_text", "source_url", "media_details.width", "media_details.height"
  ]
}

// Output
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

**Example 2:** two attachments by ID with the default fields

```jsonc
// Input
{ "where": { "ids": [ 310, 311 ] } }

// Output
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

**Example 3:** a subscriber asking for trashed attachments

```jsonc
// Input
{ "where": { "status": [ "trash" ] } }

// Error
{
  "code": "media_cannot_filter_by_status",
  "message": "Sorry, you are not allowed to filter media by this status.",
  "data": { "status": 403 }
}
```

<details>
<summary><b>Input schema</b></summary>

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
        "ids": {
          "type": "array",
          "uniqueItems": true,
          "minItems": 1,
          "maxItems": 100,
          "items": { "type": "integer", "minimum": 1 }
        },
        "slugs": {
          "type": "array",
          "uniqueItems": true,
          "minItems": 1,
          "maxItems": 100,
          "items": { "type": "string", "minLength": 1 }
        },
        "status": {
          "type": "array",
          "uniqueItems": true,
          "minItems": 1,
          "items": { "type": "string", "enum": [ "inherit", "private", "trash" ] }
        },
        "media_type": {
          "type": "array",
          "uniqueItems": true,
          "minItems": 1,
          "items": {
            "type": "string",
            "enum": [ "application", "audio", "image", "text", "video" ]
          }
        },
        "mime_type": {
          "type": "array",
          "uniqueItems": true,
          "minItems": 1,
          "items": { "type": "string", "minLength": 1 }
        },
        "post": { "type": "integer", "minimum": 0 },
        "author": {
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
      "properties": {
        "page":     { "type": "integer", "minimum": 1 },
        "per_page": { "type": "integer", "minimum": 1, "maximum": 100 }
      }
    }
  }
}
```

</details>

<details>
<summary><b>Output schema</b></summary>

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
          "id":     { "type": "integer" },
          "status": { "type": "string" },
          "date": {
            "type": "object",
            "additionalProperties": false,
            "properties": {
              "site": { "type": [ "string", "null" ], "format": "date-time" },
              "gmt":  { "type": [ "string", "null" ], "format": "date-time" }
            }
          },
          "modified": {
            "type": "object",
            "additionalProperties": false,
            "properties": {
              "site": { "type": [ "string", "null" ], "format": "date-time" },
              "gmt":  { "type": [ "string", "null" ], "format": "date-time" }
            }
          },
          "slug": { "type": "string" },
          "link": { "type": "string", "format": "uri" },
          "title": {
            "type": "object",
            "additionalProperties": false,
            "properties": {
              "raw":      { "type": "string" },
              "rendered": { "type": "string" }
            }
          },
          "caption": {
            "type": "object",
            "additionalProperties": false,
            "properties": {
              "raw":      { "type": "string" },
              "rendered": { "type": "string" }
            }
          },
          "description": {
            "type": "object",
            "additionalProperties": false,
            "properties": {
              "raw":      { "type": "string" },
              "rendered": { "type": "string" }
            }
          },
          "alt_text":   { "type": "string" },
          "media_type": { "type": "string" },
          "mime_type":  { "type": "string" },
          "source_url": { "type": "string", "format": "uri" },
          "filename":   { "type": "string" },
          "filesize":   { "type": "integer" },
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
                  "properties": {
                    "width":      { "type": "integer" },
                    "height":     { "type": "integer" },
                    "mime_type":  { "type": "string" },
                    "source_url": { "type": "string", "format": "uri" }
                  }
                }
              }
            }
          },
          "post": { "type": "integer" },
          "author": {
            "type": "object",
            "additionalProperties": false,
            "properties": {
              "id":   { "type": "integer" },
              "name": { "type": [ "string", "null" ] },
              "slug": { "type": [ "string", "null" ] }
            }
          }
        }
      }
    },
    "pagination": {
      "type": "object",
      "additionalProperties": false,
      "required": [ "total", "total_pages" ],
      "properties": {
        "total":       { "type": "integer" },
        "total_pages": { "type": "integer" }
      }
    }
  }
}
```

</details>

### `core/media-upload`

- Default `fields`: `id, status, date, slug, title.rendered, media_type, mime_type, source_url`.
- `data.date` takes `site`, `gmt` or both; `site` wins when both are sent.

**Example 1:** an image from a URL, attached to post 123

```jsonc
// Input
{
  "data": {
    "file": { "url": "https://cdn.example.test/press/team-photo.jpg" },
    "title": "Team photo",
    "alt_text": "The team on the office roof",
    "post": 123
  },
  "fields": [ "title.rendered", "alt_text", "mime_type", "source_url", "media_details", "post" ]
}

// Output
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
      "thumbnail": {
        "width": 150,
        "height": 150,
        "mime_type": "image/jpeg",
        "source_url": "https://example.test/wp-content/uploads/2026/10/team-photo-150x150.jpg"
      },
      "medium": {
        "width": 300,
        "height": 200,
        "mime_type": "image/jpeg",
        "source_url": "https://example.test/wp-content/uploads/2026/10/team-photo-300x200.jpg"
      },
      "large": {
        "width": 1024,
        "height": 683,
        "mime_type": "image/jpeg",
        "source_url": "https://example.test/wp-content/uploads/2026/10/team-photo-1024x683.jpg"
      }
    }
  },
  "post": 123
}
```

**Example 2:** a PDF from inline bytes with the default fields

```jsonc
// Input
{
  "data": {
    "file": { "base64": "JVBERi0xLjcKJeLjz9MK…", "filename": "press-kit.pdf" },
    "title": "Press kit"
  }
}

// Output
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

**Example 3:** the same PDF given as a URL

```jsonc
// Input
{ "data": { "file": { "url": "https://cdn.example.test/press/press-kit.pdf" } } }

// Error
{
  "code": "media_invalid_file",
  "message": "Only images can be uploaded from a URL.",
  "data": {
    "status": 400,
    "params": { "data[file][url]": "Only images can be uploaded from a URL." }
  }
}
```

<details>
<summary><b>Input schema</b></summary>

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
            {
              "type": "object",
              "additionalProperties": false,
              "required": [ "url" ],
              "properties": { "url": { "type": "string", "format": "uri" } }
            },
            {
              "type": "object",
              "additionalProperties": false,
              "required": [ "base64", "filename" ],
              "properties": {
                "base64":   { "type": "string", "minLength": 1 },
                "filename": { "type": "string", "minLength": 1 }
              }
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
          "properties": {
            "site": { "type": "string", "format": "date-time" },
            "gmt":  { "type": "string", "format": "date-time" }
          }
        },
        "author": { "type": "integer", "minimum": 1 },
        "post":   { "type": "integer", "minimum": 1 }
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

</details>

<details>
<summary><b>Output schema</b></summary>

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "id" ],
  "properties": {
    "id":     { "type": "integer" },
    "status": { "type": "string" },
    "date": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "site": { "type": [ "string", "null" ], "format": "date-time" },
        "gmt":  { "type": [ "string", "null" ], "format": "date-time" }
      }
    },
    "modified": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "site": { "type": [ "string", "null" ], "format": "date-time" },
        "gmt":  { "type": [ "string", "null" ], "format": "date-time" }
      }
    },
    "slug": { "type": "string" },
    "link": { "type": "string", "format": "uri" },
    "title": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "raw":      { "type": "string" },
        "rendered": { "type": "string" }
      }
    },
    "caption": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "raw":      { "type": "string" },
        "rendered": { "type": "string" }
      }
    },
    "description": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "raw":      { "type": "string" },
        "rendered": { "type": "string" }
      }
    },
    "alt_text":   { "type": "string" },
    "media_type": { "type": "string" },
    "mime_type":  { "type": "string" },
    "source_url": { "type": "string", "format": "uri" },
    "filename":   { "type": "string" },
    "filesize":   { "type": "integer" },
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
            "properties": {
              "width":      { "type": "integer" },
              "height":     { "type": "integer" },
              "mime_type":  { "type": "string" },
              "source_url": { "type": "string", "format": "uri" }
            }
          }
        }
      }
    },
    "post": { "type": "integer" },
    "author": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "id":   { "type": "integer" },
        "name": { "type": [ "string", "null" ] },
        "slug": { "type": [ "string", "null" ] }
      }
    }
  }
}
```

</details>

### `core/media-update`

- Default `fields`: `id, status, date, slug, title.rendered, media_type, mime_type, source_url`.
- `data.date` takes `site`, `gmt` or both; `site` wins when both are sent.

**Example 1:** a caption and a new alt text

```jsonc
// Input
{
  "where": { "id": 310 },
  "data": { "caption": "October 2026", "alt_text": "The team on the office roof, October 2026" },
  "fields": [ "caption", "alt_text" ]
}

// Output
{
  "id": 310,
  "caption": { "raw": "October 2026", "rendered": "<p>October 2026</p>\n" },
  "alt_text": "The team on the office roof, October 2026"
}
```

**Example 2:** detaching the press kit from its post

```jsonc
// Input
{ "where": { "id": 311 }, "data": { "post": 0 }, "fields": [ "post" ] }

// Output
{ "id": 311, "post": 0 }
```

**Example 3:** attaching it to a post the user cannot edit

```jsonc
// Input
{ "where": { "id": 311 }, "data": { "post": 7 } }

// Error
{
  "code": "media_cannot_attach",
  "message": "Sorry, you are not allowed to attach media to this post.",
  "data": { "status": 403 }
}
```

<details>
<summary><b>Input schema</b></summary>

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "where", "data" ],
  "properties": {
    "where": {
      "type": "object",
      "additionalProperties": false,
      "required": [ "id" ],
      "properties": { "id": { "type": "integer", "minimum": 1 } }
    },
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
          "properties": {
            "site": { "type": "string", "format": "date-time" },
            "gmt":  { "type": "string", "format": "date-time" }
          }
        },
        "author": { "type": "integer", "minimum": 1 },
        "post":   { "type": "integer", "minimum": 0 }
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

</details>

<details>
<summary><b>Output schema</b></summary>

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "id" ],
  "properties": {
    "id":     { "type": "integer" },
    "status": { "type": "string" },
    "date": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "site": { "type": [ "string", "null" ], "format": "date-time" },
        "gmt":  { "type": [ "string", "null" ], "format": "date-time" }
      }
    },
    "modified": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "site": { "type": [ "string", "null" ], "format": "date-time" },
        "gmt":  { "type": [ "string", "null" ], "format": "date-time" }
      }
    },
    "slug": { "type": "string" },
    "link": { "type": "string", "format": "uri" },
    "title": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "raw":      { "type": "string" },
        "rendered": { "type": "string" }
      }
    },
    "caption": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "raw":      { "type": "string" },
        "rendered": { "type": "string" }
      }
    },
    "description": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "raw":      { "type": "string" },
        "rendered": { "type": "string" }
      }
    },
    "alt_text":   { "type": "string" },
    "media_type": { "type": "string" },
    "mime_type":  { "type": "string" },
    "source_url": { "type": "string", "format": "uri" },
    "filename":   { "type": "string" },
    "filesize":   { "type": "integer" },
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
            "properties": {
              "width":      { "type": "integer" },
              "height":     { "type": "integer" },
              "mime_type":  { "type": "string" },
              "source_url": { "type": "string", "format": "uri" }
            }
          }
        }
      }
    },
    "post": { "type": "integer" },
    "author": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "id":   { "type": "integer" },
        "name": { "type": [ "string", "null" ] },
        "slug": { "type": [ "string", "null" ] }
      }
    }
  }
}
```

</details>

### `core/media-delete`

- When `MEDIA_TRASH` is off, which is the default, `permanent: true` is required: an attachment cannot be trashed there, only deleted.

**Example 1:** a permanent deletion

```jsonc
// Input
{ "where": { "id": 310 }, "permanent": true }

// Over REST
// DELETE /wp-json/wp-abilities/v1/abilities/core/media-delete/run?input[where][id]=310&input[permanent]=true

// Output
{ "outcome": "deleted" }
```

**Example 2:** the same call without `permanent` on a site where the media trash is off

```jsonc
// Input
{ "where": { "id": 310 } }

// Error
{
  "code": "media_trash_not_supported",
  "message": "The attachment does not support trashing. Set \"permanent\" to delete it.",
  "data": { "status": 501 }
}
```

<details>
<summary><b>Input schema</b></summary>

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "where" ],
  "properties": {
    "where": {
      "type": "object",
      "additionalProperties": false,
      "required": [ "id" ],
      "properties": { "id": { "type": "integer", "minimum": 1 } }
    },
    "permanent": { "type": "boolean" }
  }
}
```

</details>

<details>
<summary><b>Output schema</b></summary>

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "outcome" ],
  "properties": { "outcome": { "type": "string" } }
}
```

</details>

## Term abilities

### `core/terms-query`

- Default `fields`: `id, taxonomy, name, slug, link`.

**Example 1:** the top-level categories, two per page

```jsonc
// Input
{
  "where": { "taxonomy": "category", "parent": 0 },
  "fields": [ "name", "slug", "count" ],
  "pagination": { "per_page": 2 }
}

// Output
{
  "items": [
    { "id": 3, "name": "News", "slug": "news", "count": 12 },
    { "id": 1, "name": "Uncategorized", "slug": "uncategorized", "count": 4 }
  ],
  "pagination": { "total": 5, "total_pages": 3 }
}
```

**Example 2:** the tags of post 123 with the default fields

```jsonc
// Input
{ "where": { "taxonomy": "post_tag", "post": 123 } }

// Output
{
  "items": [
    {
      "id": 15,
      "taxonomy": "post_tag",
      "name": "beta",
      "slug": "beta",
      "link": "https://example.test/tag/beta/"
    }
  ],
  "pagination": { "total": 1, "total_pages": 1 }
}
```

**Example 3:** `parent` on a flat taxonomy

```jsonc
// Input
{ "where": { "taxonomy": "post_tag", "parent": 0 } }

// Error
{
  "code": "terms_invalid_filter",
  "message": "The \"post_tag\" taxonomy is not hierarchical.",
  "data": {
    "status": 400,
    "params": { "where[parent]": "The \"post_tag\" taxonomy is not hierarchical." }
  }
}
```

<details>
<summary><b>Input schema</b></summary>

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
        "taxonomy": { "type": "string", "enum": [ "category", "post_tag" ] },
        "ids": {
          "type": "array",
          "uniqueItems": true,
          "minItems": 1,
          "maxItems": 100,
          "items": { "type": "integer", "minimum": 1 }
        },
        "slugs": {
          "type": "array",
          "uniqueItems": true,
          "minItems": 1,
          "maxItems": 100,
          "items": { "type": "string", "minLength": 1 }
        },
        "parent":     { "type": "integer", "minimum": 0 },
        "post":       { "type": "integer", "minimum": 1 },
        "hide_empty": { "type": "boolean" }
      }
    },
    "fields": {
      "type": "array",
      "uniqueItems": true,
      "items": {
        "type": "string",
        "enum": [ "id", "taxonomy", "name", "slug", "description", "parent", "count", "link" ]
      }
    },
    "pagination": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "page":     { "type": "integer", "minimum": 1 },
        "per_page": { "type": "integer", "minimum": 1, "maximum": 100 }
      }
    }
  }
}
```

</details>

<details>
<summary><b>Output schema</b></summary>

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
      "properties": {
        "total":       { "type": "integer" },
        "total_pages": { "type": "integer" }
      }
    }
  }
}
```

</details>

### `core/term-create`

- Default `fields`: `id, taxonomy, name, slug, link`.

**Example 1:** a child category under News

```jsonc
// Input
{
  "data": { "taxonomy": "category", "name": "Product updates", "parent": 3 },
  "fields": [ "name", "slug", "parent", "link" ]
}

// Output
{
  "id": 9,
  "name": "Product updates",
  "slug": "product-updates",
  "parent": 3,
  "link": "https://example.test/category/news/product-updates/"
}
```

**Example 2:** a tag with the default fields

```jsonc
// Input
{ "data": { "taxonomy": "post_tag", "name": "beta" } }

// Output
{
  "id": 15,
  "taxonomy": "post_tag",
  "name": "beta",
  "slug": "beta",
  "link": "https://example.test/tag/beta/"
}
```

**Example 3:** the first call repeated

```jsonc
// Error
{
  "code": "term_exists",
  "message": "A term with the name provided already exists with this parent.",
  "data": { "status": 400, "term_id": 9 }
}
```

<details>
<summary><b>Input schema</b></summary>

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
    "fields": {
      "type": "array",
      "uniqueItems": true,
      "items": {
        "type": "string",
        "enum": [ "id", "taxonomy", "name", "slug", "description", "parent", "count", "link" ]
      }
    }
  }
}
```

</details>

<details>
<summary><b>Output schema</b></summary>

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

</details>

### `core/term-update`

- Default `fields`: `id, taxonomy, name, slug, link`.

**Example 1:** a description

```jsonc
// Input
{
  "where": { "taxonomy": "category", "id": 9 },
  "data": { "description": "Release notes and roadmap updates." },
  "fields": [ "name", "description" ]
}

// Output
{ "id": 9, "name": "Product updates", "description": "Release notes and roadmap updates." }
```

**Example 2:** moving the category to the top level, which changes its link

```jsonc
// Input
{
  "where": { "taxonomy": "category", "id": 9 },
  "data": { "parent": 0 },
  "fields": [ "name", "parent", "link" ]
}

// Output
{
  "id": 9,
  "name": "Product updates",
  "parent": 0,
  "link": "https://example.test/category/product-updates/"
}
```

**Example 3:** a term that is not in the taxonomy named in `where`

```jsonc
// Input
{ "where": { "taxonomy": "post_tag", "id": 9 }, "data": { "name": "Product updates" } }

// Error
{
  "code": "term_not_found",
  "message": "The requested term was not found.",
  "data": { "status": 404 }
}
```

<details>
<summary><b>Input schema</b></summary>

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
      "properties": {
        "taxonomy": { "type": "string", "enum": [ "category", "post_tag" ] },
        "id":       { "type": "integer", "minimum": 1 }
      }
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
    "fields": {
      "type": "array",
      "uniqueItems": true,
      "items": {
        "type": "string",
        "enum": [ "id", "taxonomy", "name", "slug", "description", "parent", "count", "link" ]
      }
    }
  }
}
```

</details>

<details>
<summary><b>Output schema</b></summary>

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

</details>

### `core/term-delete`

**Example 1:** deleting a tag

```jsonc
// Input
{ "where": { "taxonomy": "post_tag", "id": 15 } }

// Over REST
// DELETE /wp-json/wp-abilities/v1/abilities/core/term-delete/run?input[where][taxonomy]=post_tag&input[where][id]=15

// Output
{ "outcome": "deleted" }
```

**Example 2:** the default category is refused

```jsonc
// Input
{ "where": { "taxonomy": "category", "id": 1 } }

// Error
{
  "code": "term_is_default",
  "message": "The default category cannot be deleted.",
  "data": { "status": 400 }
}
```

<details>
<summary><b>Input schema</b></summary>

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
      "properties": {
        "taxonomy": { "type": "string", "enum": [ "category", "post_tag" ] },
        "id":       { "type": "integer", "minimum": 1 }
      }
    }
  }
}
```

</details>

<details>
<summary><b>Output schema</b></summary>

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": [ "outcome" ],
  "properties": { "outcome": { "type": "string" } }
}
```

</details>
