<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;

use Evernote\Store as EvernoteStore;
use Evernote\Client as EvernoteClient;
use EDAM\NoteStore\NoteFilter;
use EDAM\NoteStore\NotesMetadataResultSpec;
use EDAM\NoteStore\NoteMetadata;

class EnwriteExport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'enwrite:export {--all} {--auth=} {--notebook=}';

    /**
     * @var string
     */
    protected $authToken = null;

    /**
     * @var Filesystem
     */
    protected $filesystem = null;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export notes from Evernote';

    protected function updateSite()
    {
        // Check for all directories in standard Jekyll directory structure
        $dirs = [
            '_drafts' => null,
            '_includes' => null,
            '_layouts' => null,
            '_posts' => null,
            '_data' => null,
            '_site' => null,
            'res' => null,
        ];

        foreach ($filesystem->listContents() as $entry) {
            if ($entry['type'] == 'dir') {
                if (array_key_exists($entry['path'], $dirs)) {
                    $dirs[$entry['path']] = true;
                }
            }
        }

        foreach ($dirs as $dir => $sha) {
            if ($sha === null) {
                $this->comment("Creating {$dir}");
                $exporter->mkdir($dir);
            }
        }

        $this->comment("Theme '{$site->theme}'");
        $theme = dirname(__FILE__) . '/themes/' . $site->theme . '/';
        if (!is_dir($theme)) {
            return array('error',
                "No such theme '{$theme}'"
            );
        }

        if (!file_exists($theme . 'layout.html')) {
            return array('error',
                "No layout {$theme}layout.html"
            );
        }
        $exporter->save(
            '_layouts/default.html',
            file_get_contents($theme . 'layout.html'),
            file_sha('_layouts/default.html')
        );

        if (file_exists($theme . 'styles.css')) {
            $log[] = "Styles";
            $exporter->save(
                'styles.css',
                file_get_contents($theme . 'styles.css'),
                file_sha('styles.css')
            );
        }

        if (!empty($site->domain)) {
            $this->comment("Will serve domain {$site->domain}");
            $exporter->save(
                'CNAME',
                $site->domain,
                file_sha('CNAME')
            );
        }

        $xml = simplexml_load_string($site->config);
        $config = array();
        foreach ($xml->body->outline as $outline) {
            list($name, $yaml) = opml2yaml($outline);

            if ($name == '_config') {
                $fname = '_config.yml';
            }
            else {
                $fname = '_data/' . preg_replace('/\W+/u', '_', $name) . '.yml';
            }
            $this->comment("Writing {$fname}");
            $exporter->save($fname, $yaml, file_sha($fname));
        }
    }

    protected function replaceMedia($note, $path)
    {
        $mime = [
            'image/gif' => '.gif',
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/svg+xml' => '.svg',
            'audio/wav' => '.wav',
            'audio/mpeg' => '.m4a',
            'application/pdf' => '.pdf'
        ];

        $res = [];
        $reslist = '';
        if ($note->resources) {
            foreach ($note->resources as $resource) {
                $ext = !empty($mime[$resource->mime]) ? $mime[$resource->mime] : '.bin';
                if ($ext == '.bin') {
                    $this->warn("Unknown mimetype {$resource->mime}");
                }
                $resname = "res/{$resource->guid}{$ext}";
                $reshash = md5($resource->data->body, 0);
                $res[$reshash] = $resname;
                $this->comment("Writing '{$resname}'");
                $this->filesystem->put($path . $resname, $resource->data->body);
            }
        }

        $content = '';

        $noteContent = simplexml_load_string($note->content);
        $cmd = $this;
        foreach ($noteContent->children() as $c) {

            $el = preg_replace_callback(array(
                '#<en-media(.*?)/>#',
                '#<en-media(.*?)></en-media>#'
            ),
                function ($matches0) use ($res, $cmd) {
                    $attr = [];

                    foreach (preg_split('/\s+/', $matches0[1]) as $assign) {
                        if (preg_match('/(\w+)="([^"]+)"/', $assign, $matches1)) {
                            $attr[$matches1[1]] = $matches1[2];
                        }
                    }

                    if (empty($attr['hash']) || empty($attr['type'])) {
                        $cmd->error(" - missing type/hash in {$matches0[0]}");
                    }

                    if (strpos($attr['type'], 'image/') === 0) {
                        return '![Bild](' . $res[$attr['hash']] . ')';
                    }
                    else {
                        return '[' . $attr['hash'] . '](' . $res[$attr['hash']] . ')';
                    }
                },
                $c->asXML()
            );

            $content .= $el . "\n";
        }
        return $content;
    }

    protected function updateNote(EvernoteStore $store, NoteMetadata $noteMetadata, $path)
    {
        $note = $store->getNote($this->authToken, $noteMetadata->guid,
            true, // withContent
            true, // withResourcesData
            false, // withResourcesRecognition
            false // withResourcesAlternateData
        );

        $note->tagNames = $store->getNoteTagNames($this->authToken, $noteMetadata->guid);

        $url = null;
        if (!empty($note->attributes->sourceURL)) {
            $url = $note->attributes->sourceURL;
        }
        else
            foreach ($note->tagNames as $tag) {
                if (preg_match('/^url(:|=)(\S+)/i', $tag, $matches)) {
                    $url = $matches[2];
                }
            }

        $tags = join(' ', $note->tagNames);

        $created = strftime('%Y-%m-%d %H:%M:%S', $note->created / 1000);
        $updated = strftime('%Y-%m-%d %H:%M:%S', $note->updated / 1000);

        $pathPrefix = $path . strftime('/%Y-%m/', $note->created / 1000);
        $fname = $pathPrefix . strftime('%Y-%m-%d-', $note->created / 1000) . str_slug($note->title) . '.md';
        $this->comment("Saving {$fname}");
        while ($this->filesystem->has($fname)) {
            if (preg_match('#^(.*-)(\d+)(\.md)$#', $fname, $matches)) {
                $fname = $matches[1] . (intval($matches[2]) + 1) . $matches[3];
            }
            else {
                $fname = $pathPrefix . pathinfo($fname, PATHINFO_FILENAME) . '-1.md';
            }
        }

        file_put_contents('/tmp/xxx', $this->replaceMedia($note, $pathPrefix));
        $content = shell_exec('node ../htm2markdown-cli/html2markdown-cli.js /tmp/xxx');

        $content = "---
title: {$note->title}
guid: {$note->guid}
created: {$created}
updated. {$updated}
tags: {$tags}
url: {$url}
---

# {$note->title}
" . $content;

        $this->filesystem->put($fname, $content);
    }

    protected function update($notebookName = null)
    {
        $client = new EvernoteClient([
            'consumerKey' => getenv('EVERNOTE_CONSUMER_KEY'),
            'consumerSecret' => getenv('EVERNOTE_CONSUMER_SECRET'),
            'token' => $this->authToken,
            'sandbox' => getenv('EVERNOTE_SANDBOX') === 'true',
        ]);

        /** @var EvernoteStore $store */
        $store = $client->getNoteStore();
        foreach ($store->listNotebooks() as $notebook) {
            if (!$notebookName || $notebook->name == $notebookName) {
                $filter = new NoteFilter([
                    'notebookGuid' => $notebook->guid,
                ]);
                $spec = new NotesMetadataResultSpec();
                $spec->includeTitle = true;
                $spec->includeCreated = true;
                $spec->includeUpdated = true;
                $spec->includeDeleted = true;

                $this->comment("Updating '{$notebook->name}'");

                // $this->updateSite();


                $path = strtolower($notebook->name);
                $noteList = $store->findNotesMetadata($filter, 0, 10, $spec);
                foreach ($noteList->notes as $noteMetadata) {
                    $this->updateNote($store, $noteMetadata, $path);

                } // notes
            } // matching notebook
        } // all notebooks
    } // update


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $adapter = new Local(__DIR__ . '/../../../storage/export');
        $this->filesystem = new Filesystem($adapter);

        $this->authToken = $this->option('auth');
        if (!$this->authToken) {
            $this->error('Please pass an authentication token');
        }

        if ($this->option('all')) {
            $this->comment('Exporting ALL notes');
            $this->update();
        }
        elseif ($notebookName = $this->option('notebook')) {
            $this->comment("Exporting notes in {$notebookName}");
            $this->update($notebookName);
        }
        else {
            $this->error('Please pass either a notebook name or --all');
        }
    }
}
