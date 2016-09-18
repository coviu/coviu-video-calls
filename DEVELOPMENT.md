# Development Instructions

You can quickly bring up an development environment using docker.

First make sure you have [docker](https://docs.docker.com/linux/) and
[docker-compose](https://docs.docker.com/compose/) installed.

Then simply
``` bash
docker-compose up
```

You can then browse to [http://localhost:8080](http://localhost:8080) and edit
your files locally.

Also, you will need [php composer](https://getcomposer.org/) and run `composer
install` to fetch dependencies.

# How to update the plugin?

Use github & git svn, see
http://teleogistic.net/2011/05/revisiting-git-github-and-the-wordpress-org-plugin-repository/

* edit readme.txt to bump up stable tag
* edit coviu-video-calls.php bump up stable tag there, too
* commit to github: git push origin master (possibly after merging a branch)
* git checkout svn
* git merge --squash master
* git commit -m "Merging changes from Git for 1.1 release"
* git svn dcommit (to send commits to wordpress.org)
* git svn tag 0.2 (add new release)
* git checkout master
* git tag -a 0.2
  git push --tags (push tags to github also)
* git pull --rebase origin master
  git push origin master (to get svn tags to github)


Old process with SVN:
http://wordpress.org/extend/plugins/about/svn/
