# WordPress to Kirby Exporter

Export WordPress posts and attachments into folders and text files.

## What it does

- Running the exporter will create a folder called `kirby-export` inside your WordPress website's root folder.
- It will create a folder for each post using the following pattern: `YYYYMMDD_{post-slug}`.
- It will copy all images/files which are attached to a post into the post's folder and for each file add a meta data file, eg. `{filename.jpg}.txt`, containing alt and caption.
- Finally it will add a file with the pattern `YYYYMMDD_{post-slug}.txt` containing the post title, publication date, content and tags.

**Please note:**  
- All WordPress short codes will be removed.
- **No other changes will be made!** If you need to replace anything else, eg. HTML tags with markdown, please have a look at the code and extend as needed.
