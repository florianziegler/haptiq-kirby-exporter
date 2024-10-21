# WordPress to Kirby Exporter

A WordPress plugin to export pages, posts and images into folders and text files, which can be used in [Kirby](https://getkirby.com/).

* * *

## How it Works

The exporter has two options:

1. **Site URL:** You can define the domain under which the exported site will be hosted. Defaults to the site's URL. (Needs a trailing slash.)
2. **Blog Base:** The path, under which your blog will be located. Defaults to `journal`.

### Exporting Pages
- The exporter will create a folder called `kirby-content` inside your WordPress website's root folder.
- Then it will create a folder for each page, using the pattern: `{menu_order}_{post_slug}`; it will put child pages into the parent's subfolder (recursively).
- It will use the `default.txt` template name for all pages.

### Exporting Posts
- The exporter will create a folder inside `kirby-content/{blog_base}` for each post, using the pattern: `YYYYMMDD_{post-slug}`; private or draft posts will be copied into `kirby-content/{blog_base}/_drafts`
- It will then add the template file, using either `journal.txt` or `status.txt`, depending on the post's `post_format`; The file contains the post title, publication date, content, categories and tags.

### Images
- The exporter tries to identify all images inside the content for pages and posts and will try to copy them into the page's/post's folder. For each file it will add a meta data file (eg. `{filename.jpg}.txt`) containing alt and caption (if available).

### Additional Notes
- All WordPress short codes will be removed and some cleanup will be done (removing WordPress/Block editor specific attributes/classes).
- For some pages (my personal projects) I have defined custom post meta data `time` and `place`. If they exist those will be converted into Kirby fields called `Timeframe` and `Type`.
- For Posts: If a post meta entry for `_share_on_mastodon_url` exists, it will also add a `Mastodon` field with that value. (I have been using the [Share on Mastdon plugin](https://jan.boddez.net/wordpress/share-on-mastodon), which created those.)
- Content will be in HTML (for now), which means that images will be referenced by an absolute URL.

* * *

## Feedback?

Do you have any feedback? Create an [issue](https://github.com/florianziegler/haptiq-kirby-exporter/issues), or [let me know](https://florianziegler.com/contact).