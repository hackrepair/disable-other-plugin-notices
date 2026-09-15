# WordPress.org repository assets

Nothing in this folder ships inside the plugin. These files belong in the
`assets/` directory of the plugin's WordPress.org SVN repository, which sits
beside `trunk/` and `tags/` rather than inside them.

Add before or shortly after approval:

| File | Size | Purpose |
| --- | --- | --- |
| `icon-128x128.png` | 128 x 128 | Plugin icon in search results |
| `icon-256x256.png` | 256 x 256 | Retina plugin icon |
| `banner-772x250.png` | 772 x 250 | Header on the plugin page |
| `banner-1544x500.png` | 1544 x 500 | Retina header |
| `screenshot-1.png` | any | Admin screen with the panel collapsed |
| `screenshot-2.png` | any | Same screen with the panel expanded |
| `screenshot-3.png` | any | The Screen Options checkbox |

Screenshots are the only asset that also needs a matching entry. If screenshots
are added, add a `== Screenshots ==` section to `readme.txt` with one numbered
caption per file, in order.

SVN layout for reference:

```
/assets/         <- the files above
/trunk/          <- the plugin itself
/tags/1.0.0/     <- a copy of trunk at release time
```
