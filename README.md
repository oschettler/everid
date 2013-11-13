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
    pecl install oauth
    echo 'extension=oauth.so' > 20-oauth.ini

