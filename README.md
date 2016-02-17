# Fedora Video Ingesting Script


## Introduction

This is a php script developed to ingest large video file (>2G) to fedora repository using Fedora REST API directly without Islandora. 
PHP version > 5.3.0

Example usage:
```
 $php fedora_ingest.php user=fedoraAdmin pass=fedoraPassword url=http://this/fedora/URL ns=demo cmodel=islandora:sp_videoCModel \
  collection=demo:collection target=/absolute/path/to/ingest/directory log=/absolute/path/to/ingest/directory email=admin@example.com
```
target, log, email arguments are optional.
