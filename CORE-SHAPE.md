# Core Abilities Schema Quick Reference

This document is the source of truth for the shape of the core abilities: their names, input and output schemas, and behavior. Where the package code differs, the code is updated to match it.

| Ability | Status | Category | `readonly` | `destructive` | `idempotent` | REST method |
|---|---|---|---|---|---|---|
| [`core/content-query`](#corecontent-query) | Landed, [`213ecca2e9`](https://github.com/WordPress/wordpress-develop/commit/213ecca2e96) | content | `true` | `false` | `true` | `GET` |
| [`core/content-create`](#corecontent-create) | Proposed | content | `false` | `false` | `false` | `POST` |
| [`core/content-update`](#corecontent-update) | Proposed | content | `false` | `false` | `true` | `POST` |
| [`core/content-delete`](#corecontent-delete) | Proposed | content | `false` | `true` | `true` | `DELETE` |
| [`core/settings-get`](#coresettings-get) | Landed, [`b6512b1e8c`](https://github.com/WordPress/wordpress-develop/commit/b6512b1e8ca) | site | `true` | `false` | `true` | `GET` |
| [`core/settings-update`](#coresettings-update) | Proposed | site | `false` | `true` | `false` | `POST` |
| [`core/users-query`](#coreusers-query) | Landed, [`39981fd0fe`](https://github.com/WordPress/wordpress-develop/commit/39981fd0fef) | user | `true` | `false` | `true` | `GET` |
| [`core/user-create`](#coreuser-create) | Proposed | user | `false` | `false` | `false` | `POST` |
| [`core/user-update`](#coreuser-update) | Proposed | user | `false` | `false` | `true` | `POST` |
| [`core/user-delete`](#coreuser-delete) | Proposed | user | `false` | `true` | `false` | `POST` |
| [`core/media-query`](#coremedia-query) | Proposed | content | `true` | `false` | `true` | `GET` |
| [`core/media-upload`](#coremedia-upload) | Proposed | content | `false` | `false` | `false` | `POST` |
| [`core/media-update`](#coremedia-update) | Proposed | content | `false` | `false` | `true` | `POST` |
| [`core/media-delete`](#coremedia-delete) | Proposed | content | `false` | `true` | `true` | `DELETE` |
| [`core/terms-query`](#coreterms-query) | Proposed | content | `true` | `false` | `true` | `GET` |
| [`core/term-create`](#coreterm-create) | Proposed | content | `false` | `false` | `false` | `POST` |
| [`core/term-update`](#coreterm-update) | Proposed | content | `false` | `false` | `true` | `POST` |
| [`core/term-delete`](#coreterm-delete) | Proposed | content | `false` | `true` | `true` | `DELETE` |
| [`core/comments-query`](#corecomments-query) | Proposed | content | `true` | `false` | `true` | `GET` |
| [`core/comment-create`](#corecomment-create) | Proposed | content | `false` | `false` | `false` | `POST` |
| [`core/comment-update`](#corecomment-update) | Proposed | content | `false` | `false` | `true` | `POST` |

`destructive` is `true` for an ability that removes something: the four `delete` abilities, which remove an entity, and `core/settings-update`, where a `null` value deletes the stored value of a setting without a default. A `create` or any other `update` is `false`, although an update overwrites the values it is given and can have a wide effect, such as a new role in `core/user-update`. This is narrower than the definition in core, where `false` means "only additive updates". The reason is the REST method: `destructive` and `idempotent` together make the method `DELETE`, so an idempotent update marked destructive would have to be called with `DELETE`. `core/settings-update` and `core/user-delete` are not idempotent, so their method is `POST`.

Errors are `WP_Error` objects. The `// Error` part of an example shows one as the REST response body (`code`, `message`, `data`); errors are not validated against the output schema.

## Content abilities

### `core/content-query`

- Default `fields`: `id, type, status, date, slug, title.rendered`.
- `where.author` filters by the user's `id` or `slug`. There is no filter by `name` on purpose: a display name does not identify a user. The output `author` has all three: `id`, `name` and `slug`.
- Trashed posts cannot be read today: `where.status` has no `trash` value. It gets one when `core/content-delete` is added.
- A post's terms and featured image cannot be read today: the output has no `terms` or `featured_media`. It gets both, in `fields` too, when `core/content-create` is added.

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
- `data.author` takes a user ID, as `core/users-query` returns it. The output `author` is an object with `id`, `name` and `slug`.
- `data.date` takes `site`, `gmt` or both; `site` wins when both are sent. The offset is optional, and the value is read as the REST API reads `date` and `date_gmt`:
  - With an offset, such as `2026-10-21T09:30:00+01:00` or `2026-10-21T08:30:00Z`, the value is that moment, in `site` and in `gmt` alike.
  - Without an offset, such as `2026-10-21T09:30:00`, the value is site time in `site` and UTC in `gmt` (example 4).
  - The output always has the offset.
- `data.terms` takes term IDs by taxonomy, as `core/terms-query` returns them. A taxonomy the post type does not use is refused like `parent` in example 5.
- `data.featured_media` takes the ID of an image, as `core/media-query` and `core/media-upload` return it.
- A `data.status` that makes the post visible needs `publish_posts`, and a `data.author` other than the current user needs `edit_others_posts`. A `data.slug` on a post that will be `pending` also needs `publish_posts`. A `data.parent` must be a post the current user can read, of a type exposed to abilities; a missing post is refused the same way, so the refusal never tells whether a post exists. The permission callback checks all of these, so the refusal is the generic permission error, as in example 5 of `core/content-update`. A parent of another type, or one that would make a loop, is refused as an invalid `data[parent]`.

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

**Example 3:** a post with categories, a tag and a featured image

```jsonc
// Input
{
  "data": {
    "type": "post",
    "title": "Beta opens today",
    "status": "publish",
    "terms": { "category": [ 3, 9 ], "post_tag": [ 15 ] },
    "featured_media": 310
  },
  "fields": [ "status", "slug", "terms", "featured_media" ]
}

// Output
{
  "id": 127,
  "status": "publish",
  "slug": "beta-opens-today",
  "terms": { "category": [ 3, 9 ], "post_tag": [ 15 ] },
  "featured_media": 310
}
```

**Example 4:** a post scheduled for 09:00 in the site's time, with no offset in the date

```jsonc
// Input
{
  "data": {
    "type": "post",
    "title": "Beta closes today",
    "status": "future",
    "date": { "site": "2026-10-23T09:00:00" }
  },
  "fields": [ "status", "date" ]
}

// Output
{
  "id": 128,
  "status": "future",
  "date": { "site": "2026-10-23T09:00:00+01:00", "gmt": "2026-10-23T08:00:00+00:00" }
}
```

**Example 5:** an attribute the post type does not support

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
        "slug": { "type": "string" },
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
        "parent": { "type": "integer", "minimum": 0 },
        "terms": {
          "type": "object",
          "additionalProperties": false,
          "minProperties": 1,
          "properties": {
            "category": {
              "type": "array",
              "uniqueItems": true,
              "items": { "type": "integer", "minimum": 1 }
            },
            "post_tag": {
              "type": "array",
              "uniqueItems": true,
              "items": { "type": "integer", "minimum": 1 }
            }
          }
        },
        "featured_media": { "type": "integer", "minimum": 0 }
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
          "author.slug", "parent", "terms", "terms.category", "terms.post_tag", "featured_media"
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
    "parent": { "type": "integer" },
    "terms": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "category": { "type": "array", "items": { "type": "integer" } },
        "post_tag": { "type": "array", "items": { "type": "integer" } }
      }
    },
    "featured_media": { "type": "integer" }
  }
}
```

</details>

### `core/content-update`

- Default `fields`: `id, type, status, date, slug, title.rendered`.
- `data.author` takes a user ID, as `core/users-query` returns it. The output `author` is an object with `id`, `name` and `slug`.
- `data.date` takes `site`, `gmt` or both; `site` wins when both are sent. The offset is optional: without one the value is site time in `site` and UTC in `gmt`, as in `core/content-create`.
- `data.terms` replaces the post's terms in each taxonomy it names, and an empty list removes them; a taxonomy left out keeps its terms.
- `data.featured_media` takes the ID of an image; `0` removes the featured image.
- `data.slug` may be the stored slug, also the empty slug of a draft, so a post from `core/content-query` can be sent back as it is. Any other slug that is empty once sanitized is refused.
- The permission callback checks `edit_post` on the post, and the `data.status`, `data.author`, `data.parent` and `data.slug` rules as `core/content-create` does, with `publish_post` on the post for the slug of a pending post. A value that keeps the stored one needs none of these. So every permission refusal is the generic permission error (example 5). A direct `WP_Ability::execute()` call gets `ability_invalid_permissions` instead.
- A trashed post is restored by sending `data.status`: it leaves the trash with that status and gets its slug back. Without `data.status` it stays in the trash.
- The comments that went to the trash with the post come back with it, each with the status it had before, as "Restore" does on the Posts screen. This goes beyond the REST posts endpoint, which restores the post and leaves those comments hidden, in the `post-trashed` status.

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

**Example 3:** new tags and no featured image, with the categories left as they are

```jsonc
// Input
{
  "where": { "type": "post", "id": 127 },
  "data": { "terms": { "post_tag": [ 15, 16 ] }, "featured_media": 0 },
  "fields": [ "terms", "featured_media" ]
}

// Output
{
  "id": 127,
  "terms": { "category": [ 3, 9 ], "post_tag": [ 15, 16 ] },
  "featured_media": 0
}
```

**Example 4:** restoring a post from the trash as a draft

```jsonc
// Input
{
  "where": { "type": "post", "id": 123 },
  "data": { "status": "draft" },
  "fields": [ "status", "slug" ]
}

// Output
{ "id": 123, "status": "draft", "slug": "launch-notes-updated" }
```

**Example 5:** a post the user cannot edit

```jsonc
// Input
{ "where": { "type": "post", "id": 123 }, "data": { "title": "Launch notes" } }

// Error
{
  "code": "rest_ability_cannot_execute",
  "message": "Sorry, you are not allowed to execute this ability.",
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
        "slug": { "type": "string" },
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
        "parent": { "type": "integer", "minimum": 0 },
        "terms": {
          "type": "object",
          "additionalProperties": false,
          "minProperties": 1,
          "properties": {
            "category": {
              "type": "array",
              "uniqueItems": true,
              "items": { "type": "integer", "minimum": 1 }
            },
            "post_tag": {
              "type": "array",
              "uniqueItems": true,
              "items": { "type": "integer", "minimum": 1 }
            }
          }
        },
        "featured_media": { "type": "integer", "minimum": 0 }
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
          "author.slug", "parent", "terms", "terms.category", "terms.post_tag", "featured_media"
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
    "parent": { "type": "integer" },
    "terms": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "category": { "type": "array", "items": { "type": "integer" } },
        "post_tag": { "type": "array", "items": { "type": "integer" } }
      }
    },
    "featured_media": { "type": "integer" }
  }
}
```

</details>

### `core/content-delete`

- When this ability is added, `core/content-query` takes `trash` in `where.status`, so trashed posts can be read.
- A trashed post is restored with `core/content-update`, by sending `data.status`. With `permanent: true` it is deleted for good.
- The post's comments go to the trash with it, and come back with it when it is restored.

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
  "properties": {
    "outcome": { "type": "string", "enum": [ "trashed", "deleted" ] }
  }
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

- A `null` value resets a setting to its default, and stores the default so requests that do not register the setting read it too. A setting without a default has its stored value deleted, which is why the ability is `destructive`, and a `null` value for one without a stored value changes nothing.
- A `null` value for a setting without a default is refused with `settings_invalid_stored_value` while it has a stored value that `core/settings-get` leaves out, because the value does not match the setting's schema: the caller cannot see what would be deleted. A boolean stored as false can be reset. A setting with a default always takes its default. The error data maps each refused setting to its message under `params`.
- The ability is not `idempotent`, although repeating a call has no further effect: an ability that is both `destructive` and `idempotent` runs with the `DELETE` method, and this ability updates settings.
- A value that matches the schema but that the setting cannot use is refused with `settings_invalid_value`, and nothing is saved. This covers an unknown `timezone`, a `language` that is not installed, an unknown `default_post_format`, a page setting that is not 0 or the ID of a page, or is a page in the trash or an auto-draft, and a `default_category` that is not the ID of a category. The error data maps each refused setting to its message under `params`.
- The site address (`url`) and the administration email address (`email`) are read-only: `core/settings-get` returns them, but they are left out of the input, under whatever name a plugin exposes them. Settings > General only changes the administration email address after the new address confirms it.

**Example 1:** a title, a reading option and an option reset to its default

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

**Example 3:** deleting a setting without a default whose stored value no longer matches its schema

```jsonc
// Input
{ "data": { "start_of_week": null } }

// Error
{
  "code": "settings_invalid_stored_value",
  "message": "The start_of_week property has an invalid stored value, and cannot be updated to null.",
  "data": {
    "status": 500,
    "params": {
      "data[start_of_week]": "The start_of_week property has an invalid stored value, and cannot be updated to null."
    }
  }
}
```

**Example 4:** a front page that does not exist

```jsonc
// Input
{ "data": { "show_on_front": "page", "page_on_front": 999999 } }

// Error
{
  "code": "settings_invalid_value",
  "message": "Invalid page ID.",
  "data": { "status": 400, "params": { "data[page_on_front]": "Invalid page ID." } }
}
```

**Example 5:** several values the settings cannot use

```jsonc
// Input
{ "data": { "timezone": "Mars/Olympus", "language": "xx_XX" } }

// Error
{
  "code": "settings_invalid_value",
  "message": "Invalid parameter(s): data[timezone], data[language]",
  "data": {
    "status": 400,
    "params": {
      "data[timezone]": "The timezone you have entered is not valid. Please select a valid timezone.",
      "data[language]": "The language is not installed."
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
  "required": [ "data" ],
  "properties": {
    "data": {
      "type": "object",
      "additionalProperties": false,
      "minProperties": 1,
      "properties": {
        "title": { "type": [ "string", "null" ] },
        "description": { "type": [ "string", "null" ] },
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
- `roles` is optional. Without it the new user gets the site's default role, the "New User Default Role" setting. On multisite there is no default: the user becomes a member of the current site with no role.
- `email` takes up to 100 characters and `name` up to 250, the sizes of their database columns.

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
        "email":       { "type": "string", "format": "email", "maxLength": 100 },
        "name":        { "type": "string", "minLength": 1, "maxLength": 250 },
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
        "email":       { "type": "string", "format": "email", "maxLength": 100 },
        "name":        { "type": "string", "minLength": 1, "maxLength": 250 },
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

- `reassign` is required: the ID of the user who takes over the deleted user's content, or `false` to reassign nothing. Send `false` too when the user has no content. `true` is rejected.
- With a user ID, every post of the deleted user, of any post type, moves to that user, and so do their links.
- With `false`, the user's content is deleted with the account, as "Delete all content" does on the Users screen:
  - Posts and pages go to the trash. One that is already in the trash is deleted permanently, and so are all of them on a site with the trash turned off.
  - Media and posts of other post types are deleted permanently. Media goes to the trash only where `MEDIA_TRASH` is on.
  - Links are deleted.
  - Posts of a post type that is not deleted with its author, such as patterns, stay as they are.
- On multisite the user is removed from the current site instead, as "Remove" does on the site's Users screen: `outcome` is `removed` and the account stays in the network. There, `reassign: false` leaves the user's content in place; a user ID still reassigns it.
- `idempotent` is `false`: once the user is deleted or removed, the same call answers `user_not_found` with status 404. So the REST method is `POST`, with the input in the body.

**Example 1:** deleting a user and handing their posts to user 7

```jsonc
// Input
{ "where": { "id": 42 }, "reassign": 7 }

// Over REST
// POST /wp-json/wp-abilities/v1/abilities/core/user-delete/run
{ "input": { "where": { "id": 42 }, "reassign": 7 } }

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
- `where.author` filters by the user's `id` or `slug`. There is no filter by `name` on purpose: a display name does not identify a user. The output `author` has all three: `id`, `name` and `slug`.
- `media_type` in the output is the part of `mime_type` before the slash, the vocabulary `where.media_type` filters by: `image/jpeg` gives `image` and `application/pdf` gives `application`. It is a plain string and not the filter's enum, because an attachment can have a type that can no longer be uploaded, or no type at all, which gives an empty string.
- `source_url` is `null` when WordPress has no URL for the file, which happens when no file is recorded for the attachment. It is never an empty string. A size that has no URL is left out of `media_details.sizes`.

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
      "media_type": "application",
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
          "source_url": { "type": [ "string", "null" ], "format": "uri" },
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
- `data.author` takes a user ID, as `core/users-query` returns it. The output `author` is an object with `id`, `name` and `slug`.
- `data.date` takes `site`, `gmt` or both; `site` wins when both are sent. The offset is optional: without one the value is site time in `site` and UTC in `gmt`, as in `core/content-create`.
- `source_url` has the type it has in `core/media-query`, where it can be `null`. After an upload it is always a URL.

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
  "media_type": "application",
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
    "source_url": { "type": [ "string", "null" ], "format": "uri" },
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
- `data.author` takes a user ID, as `core/users-query` returns it. The output `author` is an object with `id`, `name` and `slug`.
- `data.date` takes `site`, `gmt` or both; `site` wins when both are sent. The offset is optional: without one the value is site time in `site` and UTC in `gmt`, as in `core/content-create`.
- `source_url` is `null` when WordPress has no URL for the file, as in `core/media-query`.
- `data` has no `status`, so this ability cannot restore an attachment from the trash (see `core/media-delete`).

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
    "source_url": { "type": [ "string", "null" ], "format": "uri" },
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
- When `MEDIA_TRASH` is on, an attachment goes to the trash unless `permanent` is `true`. No ability restores it from there: `core/media-update` has no `status`, unlike `core/content-update`. It is restored on the Media Library screen.

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
  "properties": {
    "outcome": { "type": "string", "enum": [ "trashed", "deleted" ] }
  }
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
- A name already used under the same parent is refused with `term_exists`. The error names the input at fault in `data.params`, as `user_exists` does, and gives the existing term in `data.term_id` (example 3).

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
  "data": {
    "status": 400,
    "term_id": 9,
    "params": { "data[name]": "A term with the name provided already exists with this parent." }
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
  "properties": {
    "outcome": { "type": "string", "enum": [ "deleted" ] }
  }
}
```

</details>

## Comment abilities

These proposals cover reader comments on posts whose type opts into `show_in_abilities`. WordPress also stores editorial notes, reactions, pingbacks and trackbacks as comments. These three abilities accept ordinary reader comments only, including legacy rows with an empty comment type.

All three use the same comment fields. The default selection is `id, post, parent, author.name, date, content.rendered, status`. An omitted or empty `fields` selects the defaults. An explicit selection always includes `id`, including on writes. Selecting `content` requests both subfields. Return `content.raw` only to callers allowed to edit the comment. Omit an object when none of its selected subfields can be returned. `date.site` and `date.gmt` express the creation time with a UTC offset, or `null` when the date is unavailable. A guest author has `author.id` of `0`. Author email and IP address are outside this proposal.

### `core/comments-query`

An ID lookup uses the same filters and response shape as a collection. Filters combine with AND, and values within `ids` or `status` combine with OR. `where.post` selects one post, `where.parent` selects direct replies, and a parent of `0` selects top-level comments. Omitting `where.status` selects approved comments, including for an ID lookup.

Readers can query approved comments on posts they can read, subject to the post's visibility and password protection. Requesting any of `hold`, `spam` or `trash` requires `moderate_comments` and the caller must be allowed to read each returned comment. Apply the same type and visibility rules to items and totals before paginating. An unknown, unreadable or non-reader comment ID contributes no row or count.

Order by comment date (GMT), newest first, then by descending ID for ties. Pages start at 1. The default page size is 10, or 100 for an explicit list of IDs. An empty match returns an empty list and zero totals. A page beyond the last page of a nonempty result returns a 404.

**Look up a comment by ID**

```jsonc
// Input
{ "where": { "ids": [ 71 ] }, "fields": [ "content.rendered" ] }
// Output
{
  "items": [ { "id": 71, "content": { "rendered": "<p>Is the meeting on Monday?</p>" } } ],
  "pagination": { "total": 1, "total_pages": 1 }
}
```

**Read direct replies to comment 71**

```jsonc
// Input
{
  "where": { "post": 42, "parent": 71 },
  "pagination": { "per_page": 1 },
  "fields": [ "parent", "author.name" ]
}
// Output
{
  "items": [ { "id": 73, "parent": 71, "author": { "name": "Maya" } } ],
  "pagination": { "total": 2, "total_pages": 2 }
}
```

**A subscriber asks for the moderation queue**

```jsonc
// Input
{ "where": { "status": [ "hold" ] } }
// Error
{
  "code": "comment_cannot_filter_by_status",
  "message": "Sorry, you are not allowed to filter comments by this status.",
  "data": { "status": 403 }
}
```

<details>
<summary><b>Input schema</b></summary>

```json
{
  "type": "object",
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
        "post": { "type": "integer", "minimum": 1 },
        "parent": { "type": "integer", "minimum": 0 },
        "status": {
          "type": "array",
          "uniqueItems": true,
          "minItems": 1,
          "items": { "type": "string", "enum": [ "approved", "hold", "spam", "trash" ] }
        }
      }
    },
    "fields": {
      "type": "array",
      "uniqueItems": true,
      "items": {
        "type": "string",
        "enum": [
          "id",
          "post",
          "parent",
          "author",
          "author.id",
          "author.name",
          "date",
          "date.site",
          "date.gmt",
          "content",
          "content.raw",
          "content.rendered",
          "status",
          "link"
        ]
      }
    },
    "pagination": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "page": { "type": "integer", "minimum": 1 },
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
          "id": { "type": "integer", "minimum": 1 },
          "post": { "type": "integer", "minimum": 1 },
          "parent": { "type": "integer", "minimum": 0 },
          "author": {
            "type": "object",
            "additionalProperties": false,
            "properties": { "id": { "type": "integer", "minimum": 0 }, "name": { "type": "string" } }
          },
          "date": {
            "type": "object",
            "additionalProperties": false,
            "properties": {
              "site": { "type": [ "string", "null" ], "format": "date-time" },
              "gmt": { "type": [ "string", "null" ], "format": "date-time" }
            }
          },
          "content": {
            "type": "object",
            "additionalProperties": false,
            "properties": { "raw": { "type": "string" }, "rendered": { "type": "string" } }
          },
          "status": { "type": "string", "enum": [ "approved", "hold", "spam", "trash" ] },
          "link": { "type": "string", "format": "uri" }
        }
      }
    },
    "pagination": {
      "type": "object",
      "additionalProperties": false,
      "required": [ "total", "total_pages" ],
      "properties": { "total": { "type": "integer", "minimum": 0 }, "total_pages": { "type": "integer", "minimum": 0 } }
    }
  }
}
```
</details>

### `core/comment-create`

Create a reader comment as the signed-in user. The post must accept reader comments from that user under the normal WordPress discussion rules, including post visibility, password protection and open comments. A positive `data.parent` identifies a readable reader comment on the same post. Omit it or pass `0` for a top-level comment. Reject an invalid parent before inserting anything.

`data.content` is the comment text. Apply the usual WordPress comment filters and reject empty content after sanitizing. WordPress assigns the author and determines moderation status. Return the stored status when selected, including `hold` for a queued comment. Duplicate and flood checks still apply. A repeated create can return a duplicate-comment error.

**Reply on a site that queues the comment for moderation**

```jsonc
// Input
{
  "data": { "post": 42, "parent": 71, "content": "The meeting is on Mondya." },
  "fields": [ "post", "parent", "status" ]
}
// Output
{ "id": 74, "post": 42, "parent": 71, "status": "hold" }
```

**Reply after comments have closed**

```jsonc
// Input
{ "data": { "post": 42, "parent": 71, "content": "Has the time changed?" } }
// Error
{
  "code": "rest_comment_closed",
  "message": "Sorry, comments are closed for this item.",
  "data": { "status": 403 }
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
      "required": [ "post", "content" ],
      "properties": {
        "post": { "type": "integer", "minimum": 1 },
        "parent": { "type": "integer", "minimum": 0 },
        "content": { "type": "string", "minLength": 1 }
      }
    },
    "fields": {
      "type": "array",
      "uniqueItems": true,
      "items": {
        "type": "string",
        "enum": [
          "id",
          "post",
          "parent",
          "author",
          "author.id",
          "author.name",
          "date",
          "date.site",
          "date.gmt",
          "content",
          "content.raw",
          "content.rendered",
          "status",
          "link"
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
    "id": { "type": "integer", "minimum": 1 },
    "post": { "type": "integer", "minimum": 1 },
    "parent": { "type": "integer", "minimum": 0 },
    "author": {
      "type": "object",
      "additionalProperties": false,
      "properties": { "id": { "type": "integer", "minimum": 0 }, "name": { "type": "string" } }
    },
    "date": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "site": { "type": [ "string", "null" ], "format": "date-time" },
        "gmt": { "type": [ "string", "null" ], "format": "date-time" }
      }
    },
    "content": {
      "type": "object",
      "additionalProperties": false,
      "properties": { "raw": { "type": "string" }, "rendered": { "type": "string" } }
    },
    "status": { "type": "string", "enum": [ "approved", "hold", "spam", "trash" ] },
    "link": { "type": "string", "format": "uri" }
  }
}
```
</details>

### `core/comment-update`

Edit the text of an approved or pending reader comment. The caller must be signed in, able to read its post, and allowed to edit the comment under Core's ordinary comment rules (`moderate_comments` or `edit_comment` for that ID). Authorship alone does not grant edit access. Apply the same content checks as on create.

Only `data.content` can be changed. The comment keeps its author, post, parent and moderation status. Repeating the same correction succeeds with the same stored text and status. An ID belonging to an editorial note or another excluded type gets the same not-found response as a nonexistent ID.

**Correct a typo while the reply is still in moderation**

```jsonc
// Input
{
  "where": { "id": 74 },
  "data": { "content": "The meeting is on Monday." },
  "fields": [ "content.raw", "status" ]
}
// Output
{ "id": 74, "content": { "raw": "The meeting is on Monday." }, "status": "hold" }
```

**Edit an ID that is not a reader comment**

```jsonc
// Input
{ "where": { "id": 90 }, "data": { "content": "Corrected text." } }
// Error
{
  "code": "comment_not_found",
  "message": "The requested comment was not found.",
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
      "required": [ "id" ],
      "properties": { "id": { "type": "integer", "minimum": 1 } }
    },
    "data": {
      "type": "object",
      "additionalProperties": false,
      "required": [ "content" ],
      "properties": { "content": { "type": "string", "minLength": 1 } }
    },
    "fields": {
      "type": "array",
      "uniqueItems": true,
      "items": {
        "type": "string",
        "enum": [
          "id",
          "post",
          "parent",
          "author",
          "author.id",
          "author.name",
          "date",
          "date.site",
          "date.gmt",
          "content",
          "content.raw",
          "content.rendered",
          "status",
          "link"
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
    "id": { "type": "integer", "minimum": 1 },
    "post": { "type": "integer", "minimum": 1 },
    "parent": { "type": "integer", "minimum": 0 },
    "author": {
      "type": "object",
      "additionalProperties": false,
      "properties": { "id": { "type": "integer", "minimum": 0 }, "name": { "type": "string" } }
    },
    "date": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "site": { "type": [ "string", "null" ], "format": "date-time" },
        "gmt": { "type": [ "string", "null" ], "format": "date-time" }
      }
    },
    "content": {
      "type": "object",
      "additionalProperties": false,
      "properties": { "raw": { "type": "string" }, "rendered": { "type": "string" } }
    },
    "status": { "type": "string", "enum": [ "approved", "hold", "spam", "trash" ] },
    "link": { "type": "string", "format": "uri" }
  }
}
```
</details>
