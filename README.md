-   [Welcome to the OJS Lucene/Solr
Plug-In!](#welcome-to-the-ojs-lucenesolr-plug-in)
- [Demo websites](#demonstrations)
-   [Should I use the Lucene/Solr
Plug-In?](#should-i-use-the-lucenesolr-plug-in)
-   [Decisions to take before enabling the
Plug-In](#decisions-to-take-before-enabling-the-plug-in)
-   [Embedded versus Remote Server
Mode](#embedded-versus-remote-server-mode)
-   [Embedded Server Mode: Installation and
Configuration](#embedded-server-mode-installation-and-configuration)
-   [Central Server Mode: Installation and
Configuration](#central-server-mode-installation-and-configuration)
-   [Troubleshooting](#troubleshooting)
-   [Push versus Pull Indexing](#push-versus-pull-indexing)
-   [Rebuilding your index, dictionaries and/or usage
statistics](#rebuilding-your-index-dictionaries-andor-usage-statistics)
-   [What else do I have to do to keep my index up to
date?](#what-else-do-i-have-to-do-to-keep-my-index-up-to-date)
-   [Customizing Solr (additional languages, stopwords,
etc.)](#customizing-solr-additional-languages-stopwords-etc.)
-   [Subscription-Based Publications](#subscription-based-publications)
-   [Using facets](#using-facets)


[![Build Status](https://travis-ci.org/pkp/lucene.svg?branch=stable-3_3_0)](https://travis-ci.org/pkp/lucene)

### Welcome to the OJS Lucene/Solr Plug-In!

This README file contains important information with respect to the
correct and secure installation of the OJS Lucene/Solr plug-in. Please
make sure you read it in full before enabling the plug-in.

While most of the code of this plug-in is published under the usual OJS
license terms, some configuration file code is based on files from the
Solr and Jetty distributions. These projects are governed by the Apache
License 2.0. We therefore included a copy of this license in the root
directory of this plug-in, see LICENSE-APACHE-2.0.txt.

For more details please visit the Solr web site: -
https://lucene.apache.org/solr/

### Demonstrations
-  https://tidsskrift.dk/
-  https://journal.fi/afs/search

### Should I use the Lucene/Solr Plug-In?

The first decision to take is whether to use the plug-in at all. The
main advantages of the plug-in's search implementation over the default
OJS search are:

-   Support for multi-lingual search. The plug-in provides
language-aware stemming of search phrases. You can define
language-specific stopword and synonym lists. And languages like
Chinese, Japanese or Korean will be supported.

-   Additional search features: The plug-in provides additional search
features like result set ordering and improved ranking. You'll also
be able to use all features of the Lucene query parser, see the
Lucene documentation for details.

-   Faster indexing: The plug-in uses the Tika document parser in the
background which is extremely fast. You'll recognize the difference
when adding galleys to an article or when having to rebuild the
index.

-   Additional document formats: The Tika document parser is able to
convert numerous documents. Most notably it supports ePub
format which is not otherwise supported by the default OJS search
implementation. You'll find a current list of all supported document
formats on the Tika website.

Caution: The default OJS search installation supports parsing of
PostScript documents. If you have articles that contain information in
PostScript documents, and you do not use any alternative galley format
that can be understood by Tika then please do not use this plug-in. Tika
is not able to parse PostScript documents.

### Decisions to take before enabling the Plug-In

This plug-in is an adapter between OJS and the Solr search server. It
requires a working Solr server somewhere on your network.

You should have a basic understanding of how the Solr search server
works before taking a deployment decision. It is in no way necessary to
understand Solr internals, but it would be useful to understand Solr's
basic architecture and how it interoperates with applications.

This plug-in can be deployed in two quite different ways: 1) with an
embedded Solr server 2) with a remote Solr server

Independently of the deployment scenario, you can choose from two index
update modes: 1) push processing: OJS will push all article changes to
the Solr server 2) pull processing: The Solr server will request article
changes in regular intervals.

The following sections will help you to take the right decisions with
respect to these configuration alternatives.

### Embedded versus Remote Server Mode

In embedded server mode, the Solr search server will run on the same
server as OJS itself. If you have only a single OJS installation on your
server then embedded server mode is almost certainly what you want. It
will be very easy to install and configure and you don't have to worry
about any Solr configuration as we provide a default configuration that
should work for you unchanged.

If you have multiple OJS installations on a single server then you still
may run in embedded server mode but please make sure that you only start
a single embedded Solr instance per server. To do so you should choose
one of your OJS installations and always run the start/stop scripts (see
below) from just that installation. If you follow the installation
procedure for the embedded server below for just one installation then
you're on the right track.

If you are a larger OJS provider and you have many OJS installations on
one or several servers then central server mode is probably better
suited to your needs. In central server mode you'll be able to install a
single Solr instance somewhere on your network and use it for all your
OJS installations. This will make your deployment much easier to monitor
and administer.

If you deploy in central server mode then you'll have to understand Solr
a bit better. You'll have to be able to install a running Solr instance
with one or more cores on your own. You can still use our Solr
schema.xml and data import configuration file unchanged and you'll
probably have to make minimal or no changes to our default
solrconfig.xml and solr.xml configuration files. But you should be able
to understand the solrconfig.xml and solr.xml and check whether it suits
your specific needs. You should also be able to set up a firewall and
write a web server configuration as Solr itself comes with no security
checks at all.

If you deploy in central server mode you'll also have to decide which
journals you want to collocate. You have to keep in mind that you only
can search across OJS installations if you collocate articles from
various installations in one core. On the other hand you should not
collocate documents from various installations if you don't want to
search across these documents. Collocating documents can have negative
effects on ranking and may require additional maintenance effort
(e.g. when having to rebuild an index from scratch after some Solr
server downtime, etc.). So please make sure you know which OJS
installations should be bundled in which core before you start deploying
your Solr server.

As a final preliminary step you'll have to make sure that your server
meets the necessary installation requirements to install Solr. This is
the OJS server if you decide to deploy in embedded server mode or the
central search server if you deploy in central server mode:

-   Operating System: Any operating system that supports J2SE 1.8 or
greater (this includes all recent versions of Linux and Windows).

-   Disk Space: The disk your Solr server will reside on should have at
least 150 MB of free disk space for the Solr/Jetty installation
files. The disc your index will reside on should have enough free
disk to accommodate at least twice the space occupied by all your
galleys and supplementary files in the files folders of your OJS
installations. In embedded mode the index will be installed into the
"files" directory of your OJS installation. So this directory should
reside on a disk with sufficient free space.

-   RAM: Memory requirements depend a lot on the size of your index. If
you have several GB of article galley files and you want best search
performance then you'll need a few GB of RAM for the Solr server and
for the operating system's file cache, too. Smaller installations
require much less memory, though. Try starting the embedded server
with default settings and only get back to it if you experience
performance problems. In most cases, default settings will probably
work for you. If the Java VM runs out of memory then augment the
corresponding memory parameters in the start script
(embedded/bin/start.sh). If that doesn't help and you see a lot of
swapping occur on your machine then you'll probably have to install
more physical RAM.

- PHP configuration: The plug-in relies on the PHP curl. Please
activate it before enabling the plug-in. If you have a large OJS
installation then rebuilding your index (see the dedicated chapter)
may require lots of memory. Please increase the 'memory\_limit' in
php.ini until you no longer get `Allowed memory size ... exhausted'
errors.

### Embedded Server Mode: Installation and Configuration

As we do not want to unnecessarily blow up our default OJS distribution
and want to make sure that you always install the latest release of
Solr, we do not distribute the Solr/Jetty java binaries with this
plug-in. You'll have to download and install them before you can use the
plug-in. This also allows you to run one Solr server for multiple OJS
installations. The following paragraphs will explain how to do this.
This will transform your OJS server into a Solr search server. If you
already installed the Solr server for one of your installations and just
want to install the plugin for another OJS installation you can ommit
steps 1) and 3) - 5).

1)  Install java: You'll need a working installation of the Java 8
Platform, Standard Edition (J2SE) Runtime Environment, Version 1.8
or higher. If you are on Linux then install a J2SE compliant Java
package. If you are on Windows you may get the latest J2SE version
from http://java.com/en/download/index.jsp.

2)  Install the OJS Lucene Plugin either from the OJS plugin gallery or
from https://github.com/ojsde/lucene

If you install the OJS Lucene plugin for an additional OJS
installation (i.e. you already have a running Solr server) please
create a symbolic link to the installation of you Solr server:

> ln -s  $OJS_INSTALL_PATH/plugins/generic/lucene/lib/solr-8.1.1 solr

3)  Download the Solr binaries:

If you are on Linux execute something like (from the OJS application
directory):

> cd plugins/generic/lucene/lib

> wget https://archive.apache.org/dist/lucene/solr/8.1.1/solr-8.1.1.zip

> unzip solr-8.1.1.zip

(You may have to install the unzip tool first...)

This should create a folder solr-8.1.1 in your lib directory. If you are
on Linux then please create a symlink pointing to this directory, e.g.:

> ln -s solr-8.1.1 solr

If you are on Windows then download and unzip the file to the lib folder
using your favorite browser and zip tool. Then rename the solr-8.1.1
folder to "solr" or use the Windows "mklink" tool to create a symlink to
the unzipped folder, e.g.:

> C:\...\ojs> cd plugins\generic\lucene\lib

> C:\...\lib> mklink /d solr solr-8.1.1

4)  Check your installation: We provide a script that finalizes the
installation and helps you to make sure that you correctly installed
Jetty and Solr.

On Linux please execute (from the OJS directory):

> cd plugins/generic/lucene/embedded/bin

> ./chkconfig.sh

On Windows this would be:

> C:\...\ojs> cd plugins\generic\lucene\embedded\bin

> C:\...\lib> chkconfig.bat

Make sure that you have the right access rights to execute the
scripts. You might have to set them:

> cd plugins/generic/lucene/embedded/bin

> chmod 774 *

If you get error messages then please return to step 2) and check
your installation.

5)  Try starting the Solr server for the first time: Go to the directory
plugins/generic/lucene/embedded/bin and execute the start script
there. On Linux this would be (from the OJS application directory):

> cd plugins/generic/lucene/embedded/bin ./start.sh

To start the Solr server as a user different from root (recommended)
use:

> sudo su -p -l <username> -s /bin/bash -c "./start.sh"

You should receive the message "Started Solr server on port 8983
(pid=XXXX). Happy searching!" and executing:

> ps -ef \| grep solr

you should see the java process of your running Solr instance.

On Windows this becomes:

> C:\...\ojs\> cd plugins\generic\lucene\embedded\bin Y C:\...\lib\>

> start.bat

And to check that Solr is actually running execute:

> C:\...\ojs\> wmic process get Caption,CommandLine \| findstr solr

This should show you the java process running Solr.

This might be a good time to change your admin password if you
haven't already; see step 6.

Alternatively you can start the Solr server from the Lucene plugin
settings page.

6)  Secure your server (IMPORTANT): While we tried to make sure that our
Solr configuration be secure by default, Solr has NOT been designed
to be directly exposed to the internet. Please make sure that you
have a firewall in place that denies public access to IP port 8983.
If for some reason you do not have a firewall in place right now,
then make sure you change the default Solr admin password
immediately.

In the standard configuration Basic Authentication is used, and
configured in the file

plugins/generic/lucene/embedded/solr81/security.json. The standard
admin username and password are solr and SolrRocks

If you want to change to another authentication method, please
consult the [documentation](https://lucene.apache.org/solr/guide/8\_1/authentication-and-authorization-plugins.htm)

One way to change the password once the server is running
(i.e. after you have completed the step 5) is to use curl. To do this,
complete step 5 and use the following command from the commandline,
replacing PLEASE CHANGE with the new password:

Temporarily make security.json writeable:

> sudo chmod g+w security.json

Change password:

> curl --user solr:SolrRocks  http://localhost:8983/api/cluster/security/authentication -H  'Content-type:application/json' -d '{"set-user": {"solr":"PLEASE> CHANGE"}}'

Secure credentials file:

> sudo chmod g-w security.json

This command will change the file
plugins/generic/lucene/embedded/solr81/security.json, so if you upload
this file again, the password will be reset to SolrRocks

7)  Now open up your web browser and log into your OJS journal manager
account.

> Go to "Settings -\> Website -\> Plugins" and enable the "Lucene Search  Plugin" (under "Generic Plugins").

Enter the search endpoint as: `http://127.0.0.1:8983/solr/ojs/search` and
provide the admin username and password (see step 6).

8)  Build your lucene index:

Back to the command line go to the tools directory and execute the
script to rebuild your index.

On Linux and from the OJS directory this becomes:

> php tools/rebuildSearchIndex.php -d

You should see output similar to this:
```
LucenePlugin: Clearing index ... done
LucenePlugin: Indexing "lucene-test" ... 412 articles indexed
LucenePlugin: Indexing "test" ... 536 articles indexed
LucenePlugin: Rebuilding dictionaries ... done
```

On Windows execute:

>  C:\...\ojs> cd tools

>  C:\...\tools> php rebuildSearchIndex.php -d

Please make sure that the output really includes the "LucenePlugin"
string. Otherwise your plug-in was not correctly activated.

9)  Execute some searches

  Go to your OJS web frontend and test whether searching with Solr works as expected.

### Central Server Mode: Installation and Configuration

Solr can be installed and deployed in many different ways and there is
no one best deployment for large OJS providers. You'll have to
understand Solr sufficiently well to be able to install and configure a
Solr server before you should try to use it with OJS.

We assume that you have this prior knowledge and will only describe the
steps specific to the OJS Lucene/Solr plug-in:

1)  Please decide which journals you would like to collocate in which
cores and make a list of required cores.

2)  Install Jetty and Solr binaries without configuring anything yet.
You can always use the embedded installation from the plug-in as a
guideline but you'll have to make your own choices with respect to
the directory structure and integration of Solr/Jetty with your
operating system. If you already use tomcat on your server you can
deploy Solr without having to install Jetty. Your OS distribution
may also provide installation packages for Solr and Jetty, so use
your own judgement to establish a basic installation adequate to
your server environemnt.

3)  As a configuration baseline you can copy the files in
`plugins/generic/ lucene/embedded/etc` and
`plugins/generic/lucene/embedded/solr` to the corresponding places
in your Jetty and Solr installation, respectively.

You'll probably have to change paths and security configuration in
jetty.xml, webdefault.xml and solrconfig.xml.

In most cases you can leave dih-ojs.xml and schema.xml unchanged.
You may want to have a look at the analysis and query chains in
schema.xml if you have specific analysis requirements. Be careful,
though, not to change any field definitions as this may have
unexpected effects and break the OJS/ Solr indexing protocol unless
you also edit dih-ojs.xml.

Sooner or later you'll probably want to edit the stopword lists and
you may want to insert synonym lists or exchange the stemmers.

In any case you'll have to look at the Solr core configuration in
solr.xml and you'll have to configure the corresponding search
handlers in security.json.

You may also want to change the BASIC authentication password in
realm.properties. But please do not rely on BASIC authentication
alone to secure your Solr server.

The start and stop scripts can be adapted to work in your
environment. If you are using OS packages then these packages
probably already provide init scripts so that you do not need
start/stop scripts at all.

4)  Once you have a working Solr configuration you'll have to enable the
Solr plug-in in all OJS installations that you want to connect to
your Solr server. To do so, open up your web browser and log into
your OJS journal manager account.

> Go to "Journal Manager -\> System Plugins -\> Generic Plugins" and enable the "Lucene Search Plugin".

5)  Go to the plug-in's setting page and enter the URL of the search
handler corresponding to the core that you want to index the journal
handler. These are the search handlers you configured in
solrconfig.xml.

Do not forget to change the BASIC HTTP authentication credentials if
you changed them for your Solr server.

Finally, you'll have to enter an installation ID that is unique
within the core that you'll index that OJS installation in. If you
index journals from three different OJS installations in one core
then you'll need three distinct installation IDs.

6)  Build your lucene index:

For each installation separately you'll have to drop to the command
line, go to the tools directory and execute the script to rebuild
your index for that installation:

> cd tools php rebuildSearchIndex.php -d

You should see output similar to this:
``` LucenePlugin: Clearing
index ... done \> LucenePlugin: Indexing "lucene-test" ... 412
articles indexed \> LucenePlugin: Indexing "test" ... 536 articles
indexed \> LucenePlugin: Rebuilding dictionaries ... done
```
Please make sure that the output really includes the "LucenePlugin"
string

7)  Execute some searches

Go to the OJS web frontend of each installation and test whether
searching with Solr works as expected.

### Troubleshooting

If you have trouble INSTALLING or CONFIGURING the Solr server then you
may try the following:

-   If you get `allowed memory size ... exhausted` errors while doing an
index rebuild then you have to increase your `memory\_limit` setting
in php.ini. Doing a huge index rebuild may require considerable
memory resources. This cannot be fixed easily as it is due to memory
leaks in the PHP mysql extension used by OJS.

-   Check whether your Solr server is really running:

> ps -ef \| grep solr

If the server is not running then try the following steps:

1)  Completely delete the folder `files/lucene`.

2)  Execute the script

> plugins/generic/lucene/embedded/bin/start.sh

3)  Check whether your Solr server is now running.

4)  If your server is running then execute:

> php tools/rebuildSearchIndex.php -d

5)  Execute a test search request in OJS.

-   Once the server is running, check whether your index contains data.
To do so, open a browser window and navigate to the URL
`http://localhost:8983/solr/`. The browser should ask you for the
user name and password you configured for your Solr web server. Once
you entered your credentials, you should see a page with information
about your SOLR-instance. In the left menu, you find a Core-Selector
dropdown. After selecting the OJS Core, you will find details about
the index content. The field 'numDocs' shows how many articles have
actually been indexed. Remaining XML can give you an idea
whether title, abstract, full text, etc. have been correctly
indexed.

If your index is incomplete then try the following steps:

1)  Stop the Solr server executing the script

> plugins/generic/lucene/embedded/bin/stop.sh

2)  Completely delete the folder `files/lucene`

3)  Execute the script

> plugins/generic/lucene/embedded/bin/start.sh

4)  Execute the command:

> php tools/rebuildSearchIndex.php -d

5)  Check the index again via the URL

> http://localhost:8983/solr/\#/ojs/core-overview

6)  Execute a test search request in OJS.

-   If your indexing went through but you inly find metadata and no
galley content please verify your OJS "Site Access Options". Only
public content will be index by Solr.

-   If your index contains data and you still do not get any search
results in OJS then try deleting your cache.

You can execute:

> rm cache/fc-plugins-lucene-fieldCache.php

Alternatively you can delete the cache via OJS' administrator tools.

Now execute another test search request in OJS.

-   If you do get search results, but autosuggestion does not work, make
sure that autosuggestion has been activated in the plugin settings
and the dictionaries have been build. You can build the dictionaries
from the plugin settings page or by executing rebuildSearchIndex.php
with the -d flag from the Commandline.

-   If the previous steps didn't give you a working search index then
have a look at the file 'files/lucene/solr.log'. This is the log
written by the Solr server. Sometimes a solution to your problem may
be obvious from the error messages, e.g. in the case of permission
problems or erroneous symlinks. If you have difficulties in
interpreting error messages in the log then please register a bug
report at 'https://github.com/pkp/pkp-lib\#issues' and post the most
recent error message. This will help OJS developers to resolve your
problem.

If you get errors when STARTING or STOPPING the solr server (e.g. the
server does not start or stop from the web interface or you cannot stop
the server via scripts):

-   Please make sure that you always start/stop the server in the same
way: Either through shell scripts or through the web interface.
Otherwise it may happen that you encounter permission problems that
have to do with the fact that your web server runs under a different
user account than your administrative login. If you wish to run Solr
under a specific account then you should never use the web interface
as it can only run the Server under the web server account. You may
want to write init scripts in this case that start the process with
the user of your choosing.

-   More generally, both on Windows and \*nix, you'll have difficulties
when starting Solr from a different user than stopping it (e.g. on
Windows from an Administrator prompt and then trying to stop from a
user prompt).

-   If you have permission problems then please make sure that the
'files/lucene' directory and all subfolders and files therein are
readable and writable by the user account that runs Solr. You may
have to change permissions if you want to start the server
differently than before.

If you find that binary files (i.e. galleys or supplementary files) are
NOT being correctly INDEXED:

-   Please make sure that the 'base\_url' parameter in your OJS
directory points to the correct URL (without trailing slash).

If search is performing more slowly than anticipated check your custom
ranking settings. If custom ranking is enabled, this will add an
overhead to searches proportional to the size of your index.

### Push versus Pull Indexing

Article indexing can be initiated on the client side (push processing)
or on the server side (pull processing). Both options have their
strengths and weaknesses.

Advantages of push configuration:

-   Indexing can be done on-demand when new documents are added to OJS.
This guarantees that the index is always up-to-date.

-   Simple configuration, works out-of-the-box.

Advantages of pull configuration:

-   Pull processing makes the OJS user interface more responsive as no
synchronous indexing will take place. Marking an article for update
is as fast as a single database access. This can make a perceivable
difference when articles have many galleys or supplementary files
attached.

-   Push processing means that editorial activity during daytime will
cause update load peaks on the Solr server exactly while it may also
experiences higher search volume. This load can be quite erratic and
fluctuating in larger system environments and therefore difficult to
balance. In pull mode indexing schedules can be configured and
co-ordinated on the server side to balance document import load on
the central search server and keep it to off-hours.

-   Pull provides a process that makes it easy to restart indexing after
client- side outage or other indexing problems. All meta-data
retrieved from the OJS clients will be saved to a staging area on
the file system. Files that cannot be successfully indexed will be
moved to a special folder where they can be checked and corrected
manually and then re-submitted to the indexing processing chain. A
full index rebuild is not required.

-   In the case of index corruption, the last known-to-be-good backup
can be restored and archived meta-data files can then be used to
replay changes to the index. This means that even in the case of
index corruption a full index rebuild is not required.

We recommend using the simpler push configuration by default. If you
then start to encounter situations in which pull processing would be an
advantage you can switch to the more complex but also more scalable pull
configuration.

To do so you have to: - edit the configuration file
('plugins/generic/lucene/embedded/etc/pull.conf') of the pull processing
script on the Solr server (see the inline comments there), especially
change the Authentification Credentials if you have changed them, -
enable pull-processing in the Lucene plugin settings of all OJS clients,
- schedule the pull download script
(`plugins/generic/lucene/embedded/bin/ pull.sh`) on the Solr server,
e.g. as a cron job. We recommend scheduling this script once or twice a
day during off-hours when server load is low. - schedule the load
processing script (`plugins/generic/lucene/embedded/bin/ load.sh`) on
the Solr server, e.g. as a cron job. We recommend scheduling this script
about every 15 minutes. It polls the staging folder and will do nothing
if no files are staged for load. - regularly monitor the staging, reject
and archive queues on the Solr server.

To test whether your pull processing configuration works in principle
you can execute the following actions in order:

1)  Make sure that you edited the configuration file and enabled
pull-processing in OJS clients as described above.

2)  Trigger a full index rebuild on one of your OJS clients (either on
the Lucene plugin settings page or through the
`tools/rebuildSearchIndex.php` script).

3)  Execute the pull.sh script manually on your Solr server. You should
see output confirming that one or more XML files have been staged
for loading.

4)  Execute the load.sh script manually on your Solr server. You should
see output confirming that one or more XML files have been
successfully processed and archived.

5)  If you get rejected files then check your configuration file and if
that doesn't help look at the file files/lucene/solr.log for some
indication of what could have gone wrong.

### Rebuilding your index, dictionaries and/or usage statistics

There are a few maintenance operations that cannot be fully automated
out-of-the-box:

1)  If you are using search features like "dictionary-based
auto-suggestions" or "alternative spelling suggestions", then you
have to make sure that the term dictionaries used for these features
will be synchronized to the index after larger index updates or, if
you like, on regular intervals, e.g. once a day. Updating
dictionaries cannot be done automatically after every index update
as rebuilding dictionaries can be quite costly.

If you have a central search index shared between several OJS
installations then you only have to make the dictionary update once
for all installations from any of the connected OJS clients.
Dictionary updates are always global operations.

Dictionary updates have to be scheduled independently of the index
update mode (pull or push).

2)  Under certain circumstances it may happen that your index becomes
outdated or corrupt. If, for example, you are using push indexing
and your Solr server is offline while editors make changes to a
journal then your Solr index will be out of sync afterwards.

It also may happen that Solr encounters an error while updating its
index. In that case you'll find an error message in your Solr log.
In the embedded deployment scenario this log can be found in
"files/lucene/solr-java.log".

If you customize your Solr schema configuration (e.g. to add
additional languages) then your index needs to be syncronized, too.

In these cases you'll have to rebuild your search index so that
searches can be reliably executed again.

There are two possibilities to execute these administrative tasks:

1)  On the command line:

To update your index and dictionaries, execute the
'rebuildSearchIndex' script. This script can be used to automate
index maintenance. You may want to create a cronjob for example that
rebuilds dictionaries every night.

The script has the following usage pattern:

> php tools/rebuildSearchIndex.php \[options\] \[journal\_path\]

journal\_path:

This is an (optional) journal URL path as configured in OJS. Giving
a journal path will only re-index articles from that journal. Giving
no journal path wil re-index all journals of the installation.

The options are:

> -d Rebuild dictionaries.

> -b Update usage statistics (see Ranking by Usage Statistics,
> above).

> -n Do not re-index any articles. If this option is given together
> with a journal path then the journal path will be ignored.

> -h Display usage information.

Examples:

To rebuild all journals without rebuilding dictionaries:

> php tools/rebuildSearchIndex.php

To rebuild only the dictionaries:

> php tools/rebuildSearchIndex.php -d -n

To rebuild the dictionaries, refresh usage statistics and re-index
all journals of an installation:

> php tools/rebuildSearchIndex.php -d -b

To rebuild the index of a single journal without rebuilding
dictionaries:

> php tools/rebuildSearchIndex.php some-journal-path

Automated maintenance:

Of course you can automate maintenance by installing a cron job,
e.g. to regularly update the dictionary and/or usage statistics.

2)  Through the OJS web page:

Please go to the Lucene plugin settings page and see the
administrative section there. The page has appropriate inline help.

### What else do I have to do to keep my index up to date?

Once correctly installed and running, the Solr/Lucene plug-in does not
require any further maintenance. Just monitor performance and resource
usage of the Solr server as you would for any other process.

### Customizing Solr (additional languages, stopwords, etc.)

All Solr configuration files come with inline comments that hint you
about what can be changed and what you better leave alone so as not to
interrupt communication between OJS and Solr.

Common customizations will above all concern the Solr analysis process,
e.g. customizing multi-language support, adding additional stopwords,
introducing synonyms, etc.

The default configuration already comes equipped with support for all
OJS languages when Solr/Lucene supports these. Languages that are not
directly supported by Solr will be treated in a language-agnostic way so
that you should at least get basic search support.

If you want to customize language support (e.g. remove a stemmer, add
stopwords, add synonyms, etc.) then you can do so in the schema file
(plugins/generic/ lucene/embedded/solr/conf/schema.xml). Please make
sure you understand the Solr analysis process first. You may want to
read: - https://wiki.apache.org/solr/AnalyzersTokenizersTokenFilters -
https://wiki.apache.org/solr/LanguageAnalysis

Our schema works with dynamic types. There is one field type for each
language. The OJS locale-language assignment is then done with dynamic
fields. You find both, the type definition and the dynamic field
definition in the schema.xml file.

Let's take support for German as an example: First the schema.xml file
contains the type definition:

<!-- German -->
```xml
<fieldType name="text_de" class="solr.TextField" positionIncrementGap="100">
<analyzer> ... </analyzer> </fieldType>
```

Then, further down the schema.xml file you`ll find the dynamic field
definition that points to the type definition:
```xml
<dynamicField name="*_de_DE" type="text_de" />
```

If you want to customize support for your language then you'll just have
to change the field type definition for your language. To introduce a
new locale you insert a field type definition and a dynamic field. The
default import configuration will deal automatically with new languages.

To add additional stopwords you have to find the stopword list
corresponding to the language you are customizing. You find the default
stopword lists in the folder
`plugins/generic/lucene/embedded/solr81/conf/lang`. You can edit these
files manually and then restart the Solr process. This should be enough
to introduce the new stopwords.

We use a so-called "bigram" approach by default to index Chinese,
Taiwanese or Japanese texts. If you have texts in simplified Chinese
only you may want to try out the following alternative configuration in
your schema.xml:
```xml
<dynamicField name="*_zh_CN" type="text_csimp" indexed="true"  stored="true"
  multiValued="false" /> ...
```
To use this filter, see solr/contrib/analysis-extras/README.txt for
instructions on which jars you need to add to solr/lib.

Still another approach to indexing Chinese, Korean or Japanese could be
the following configuration:

```xml
<fieldType name="text_cjk" class="solr.TextField" positionIncrementGap="100">
<analyzer> <tokenizer class="solr.LowerCaseTokenizerFactory"/>
<filter class="solr.EdgeNGramFilterFactory" minGramSize="1"
	  maxGramSize="2" side="back"/> </analyzer> </fieldType>
```
If you are searching in mixed-language fields (e.g. titles that contain
both English and Chinese versions of the same text) then searching for
words with quotes (e.g. '+China +"中国"') may be helpful to avoid false
positives.

### Subscription-Based Publications

In order for the Solr server to gain access to subscription-only content
on the OJS server, its server IP will have to be authorized as an
"institutional subscriber" of the journals to be indexed.

We ensure that subscription checks will be valid in a pull scenario (see
the description of push vs. pull indexing scenarios above). This means
that our "pull" web service for articles will only provide access to
subscription- protected articles if the requesting server can properly
authenticate itself and has been authorized to access all articles.

The IP-based subscription mechanism is exposed to IP-forging attacks. If
such an attack is a realistic risk in your case then you should set up a
proxy that denies all access to the XML webservice endpoint from outside
your internal network.

The pull service endpoint URL is: -
> http://www.your-host.com/index.php/index/lucene/pullChangedArticles

If you are using path-based URLs then your endpoint may change
accordingly, e.g. -
> http://www.your-host.com/index/lucene/pullChangedArticles

You can issue a parameter-less GET request to the given endpoint to
check that you are looking at the right URL. But please do not do this
when you are already productive as changes to articles will only be
published once. So if you look at the pull service manually you'll have
to do a full index rebuild afterwards to make sure that your index is
fully up-to-date.

### Using facets

Lucene supports faceted search. In faceted search, in addition to the
standard set of search results, we also get facet results, which are
lists of subcategories for certain categories. For example, for the
keyword facet, we get a list of relevant keywords; for the author facet,
we get a list of relevant authors; and so on. In most UIs, when users
click one of these subcategories, the search is narrowed, or drilled
down, and a new search limited to this subcategory (e.g., to a specific
keyword or author) is performed.

Faceting can be enabled or disabled for different categories
(discipline, keyword, method/approach, journal, author and publication
date) in the plugin settings form.

If relevant facets are found for a search request, a facet block will be
displayed in the sidebar. You can determine the position of the facets
block under 'Settings / Website / Appearance' below Sidebar Management.
It is recommended to move the Lucene Faceting Block to the top of the
sidebar.
