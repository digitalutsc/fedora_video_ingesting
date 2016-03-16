# Fedora Video Ingesting Script


## Introduction

This is a php script developed to ingest large video file (>2G) to fedora repository using Fedora REST API directly without Islandora. 
PHP version > 5.3.0 with libcurl library.

## Available arguments
  * user : fedora username (required)
  * pass : fedora user password (required)
  * url : fedora REST API url, such as ttp://localhost:8080 (required)
  * ns : the namespace of the target collection on Islandora
  * cmodel : the PID of the Islandora Content Model, can be either islandora:sp_videoCModel or islandora:oralhistoriesCModel (required)
  * collection : the PID of the target collection on Islandora (required)
  * target : the absolute directory path on the server which holds all ready-for-ingest files (required)
  * log : the absolute directory path on the server to write the log file (optional)
  * email : the email address to receive the log file when the job is done. The server must support php mail() function (optional)
  
## Folder structure of ready-for-ingest files
  * top-ingest-folder/
  ** ingest-object-1/
  *** video1.mov
  *** video2.mp4
  *** mods.xml
  *** tn1.jpg or tn1.png
  ** ingest-object-2/
  *** video2.mov
  *** video2.mp4
  *** mods.xml
  *** tn2.jpg or tn2.png

## Example usage:
```
 $php fedora_ingest.php user=fedoraAdmin pass=fedoraPassword url=http://this/fedora/URL ns=demo cmodel=islandora:sp_videoCModel \
  collection=demo:collection target=/absolute/path/to/ingest/directory log=/absolute/path/to/ingest/directory email=admin@example.com
```