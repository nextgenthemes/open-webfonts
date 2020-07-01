# Google Webfont Magician by nextgenthemes.com

Probably just head over to [Google Webfont Downloader](http://nextgenthemes.com/google-webfont-downloader/)

# What is does

Downloads Google Fonts in `.woff2` only and created the exact same directory structure Google has in its static servers.

1. Downloads a provided `.css` file form google fonts.
2. Detects all kind of info with a `regex`.
3. Downloads all fonts.
4. Packages files in `css/xxxxxx.css` and `fonts/id/version/xxxxx.woff2`.
5. Packages license files from the original Google repo into the `fonts/id/version/xxxxxx.txt`.
6. Creates a `.zip` for the requested package.

This script does things differently then https://google-webfonts-helper.herokuapp.com that is outdated and seems unmaintained. I never looked at the code and this kind of came into existence based in a older script I wrote to actually utilize google-webfonts-helper and download all fonts (see git history mess for that). After I realized that the new `display: swap` CSS is not supported I came up with this. No need to create a new UI.

This script works on the CLI as well as on a PHP server (utilizing some WordPress escaping functions).

When run on CLI you can provide an integer as first argument and it will download the most popular `x` fonts in all styles into the `/webfonts/` directory (rename it first). If a style does not exist the css will simply not have it included so `php open-webfonts.php 9999` is actually how I created `/webfonts/` in the repo.

## Bug reports and improvement requests

Yes please.

At this point the https://google-webfonts-helper.herokuapp.com/api/fonts is used to get a list of all font families to iterate over. Also because they are returned by popularity order it was easy to make the scripts download the most popular fonts only. If there is a way to do that directly from Google let me know or we actually we could used the `.pb` files from the original repo ... to compile a list, that is probably how the helper is doing it ... Ideally I like to get rid of that dependency.

`ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900` is this the correct query part for all styles? 18 was the most I found in a font.

As a CLI script I think its OK but as a webapp the script is very messy. If you good at php and security would love to hear from you. The script creates directories and zip files on my webserver. It overwrites the zip and it could be a concern if two people request the exact same fonts at the same time. 

I think the version to download just a few fonts could be recreated in JavaScript, downloading the files to session storage, packaging the files with `JSzip` and serving it to the user without a need of a webserver backend handling anything else then serving the `.js` app. Maybe there are other things out that, I hope not because I never looked and got obsessed and put many hours into this.

I suck at writing, this readme probably has to much ranting and bad grammar, if that is your thing.

## Self Host Fonts Available From Google Fonts

Yes go to [Google Webfont Downloader](http://nextgenthemes.com/google-webfont-downloader/).
Or download all fonts as a package, see below.

## Download All Open Webfonts

You can download all Open Webfonts in a simple ZIP snapshot from <https://github.com/nextgenthemes/open-webfonts/archive/master.zip> and place the /webfonts/ directory (or just the fonts you like) on your webserver. You already got the file structure google uses now. So any Google fonts `.css` will work for you if you replace `https://fonts.gstatic.com/s/` with either your relative or absolute url to where your fonts are served.

#### Sync With Git

You can also sync the collection with git so that you can update by only fetching what has changed. To learn how to use git, Github provides [illustrated guides](https://guides.github.com) and a [youtube channel](https://www.youtube.com/user/GitHubGuides), and an [interactive learning lab](https://lab.github.com). 
Free, open-source git applications are available for [Windows](https://git-scm.com/download/gui/windows) and [Mac OS X](https://git-scm.com/download/gui/mac).

## Licensing

It is important to always read the license for every font that you use.
Each font family directory contains the appropriate license file for the fonts in that directory. 
The fonts files themselves also contain licensing and authorship metadata.

Most of the fonts in the collection use the SIL Open Font License, v1.1.
Some fonts use the Apache 2 license. 
The Ubuntu fonts use the Ubuntu Font License v1.0. 

The SIL Open Font License has an option for copyright holders to include a Reserved Font Name requirement, and this option is used with some of the fonts. 
If you modify those fonts, please take care of this important detail.
