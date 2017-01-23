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

Follow the squash release process at the end of that blog post - Wordpress don't like having every commit pushed at them in one fell swoop.

* edit readme.txt to bump up stable tag
* edit coviu-video-calls.php bump up stable tag there, too
* commit to github: git push origin master (possibly after merging a branch)
* Now merge your latest commits into the svn branch:
  git checkout svn
  git merge --squash master
  git commit -m 'merging changes from git for 0.4 release'
* Now push this squashed commit to wordpress svn and tag it there:
  git svn dcommit (this also recreates the commit to connect it to svn)
  git svn tag 0.4 (add new release)
  [recently stopped working - use svn directly instead:
  svn cp http://plugins.svn.wordpress.org/coviu-video-calls/trunk http://plugins.svn.wordpress.org/coviu-video-calls/tags/0.2 -m "creating tag 0.2"]
* You're done on svn - now just keep some connection to github:
  git push (save the svn branch to github)
  git checkout master
  git tag -a 0.4
  git push --tags (push tags to github also)


Old process with SVN:
http://wordpress.org/extend/plugins/about/svn/
