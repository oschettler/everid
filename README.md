# everid

Websites from Evernote notebooks

## Installation

### Submodules

    git submodules init
    git submodules update

### Composer

    curl -sS https://getcomposer.org/installer | php
    php composer.phar install

### OAuth

    sudo -i
    source ~olav/.bash_profile
    apt-get install libpcre3-dev
    pecl install oauth
    echo 'extension=oauth.so' > /etc/php5/mods-available/oauth.ini
    cd 

### Database

You need an SQlite database in ../db/everid.sqlite that is currently not created automatically. Use your favorite SQlite tool or the sqlite3 command line and create the following tables:

    DROP TABLE IF EXISTS "account";
    CREATE TABLE "account" (
      "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
      "evernote_id" integer NULL,
      "username" text NULL,
      "token" text NOT NULL,
      "name" text NULL,
      "notebook" text NULL,
      "theme" text NOT NULL DEFAULT 'bootstrap',
      "config" text NULL,
      "domain" text NULL,
      "github_username" text NULL,
      "github_repo" text NULL,
      "github_token" text NULL,
      "synched" integer NULL,
      "created" integer NOT NULL,
      "updated" integer NOT NULL
    );


    DROP TABLE IF EXISTS "note";
    CREATE TABLE "note" (
      "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
      "guid" text NOT NULL,
      "created" integer NOT NULL,
      "updated" integer NOT NULL,
      "title" text NOT NULL,
      "structure" text NOT NULL
    );

    CREATE UNIQUE INDEX "note_guid" ON "note" ("guid");


    DROP TABLE IF EXISTS "theme";
    CREATE TABLE "theme" (
      "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
      "name" text NOT NULL,
      "title" text NOT NULL,
      "created" integer NOT NULL,
      "updated" integer NOT NULL
    );

Make sure that the database is writable by the www-data user:

    sudo chown -R www-data db

### Settings

Create a file ../settings.ini by copying the example settings.ini.default
 
