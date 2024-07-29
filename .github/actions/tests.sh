#!/bin/bash

set -e

# Install JRE and Lucene
sudo apt-get install openjdk-11-jre
cd plugins/generic/lucene/lib
wget https://archive.apache.org/dist/lucene/solr/8.1.1/solr-8.1.1.zip
unzip solr-8.1.1.zip
ln -s solr-8.1.1 solr
cd ../../../../
# Ensure we don't get any false positives from the built-in search index by deleting its contents.
#echo "DELETE FROM submission_search_object_keywords; DELETE FROM submission_search_keyword_list; DELETE FROM submission_search_objects;" | ~//datasets/tools/dbclient.sh
if [[ "$TEST" == "mysql" || "$TEST" == "mariadb" ]]; then
  sudo mysql -u root -e "use ojs-ci; DELETE FROM submission_search_object_keywords; DELETE FROM submission_search_keyword_list; DELETE FROM submission_search_objects; ";
elif [[ "$TEST" == "psql" ]]; then
  psql -d ojs-ci -c "DELETE FROM submission_search_object_keywords;"
  psql -d ojs-ci -c "DELETE FROM submission_search_keyword_list;"
  psql -d ojs-ci -c "DELETE FROM submission_search_objects;"

fi

#Install plugin version (not included in dataset DB dump). FIXME: Build and install plugin through upload?
php lib/pkp/tools/installPluginVersion.php plugins/generic/lucene/version.xml

# Check Lucene configuration and start server
# (Lucene plugin does not like relative file paths; make absolute in config file)
sed -i -e "s/files_dir = files/files_dir = \/home\/runner\/ojs\/files/" config.inc.php
cd plugins/generic/lucene/embedded/bin
./chkconfig.sh
./start.sh

# Run plugin test set
cd  ~/$APPLICATION

npx cypress run --config '{"specPattern":["plugins/generic/lucene/cypress/tests/functional/*.cy.js"]}'
