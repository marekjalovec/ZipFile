ZipFile
=======

Pass multiple files in one ZIP archive to the output stream with minimum memory footprint


How to use
----------

Simply require the class file.

    require('ZipFile.php');

You can add files during file creation with array of file paths

    // Create ZIP archive with the list of file paths, all files will be in the root of the archive
    $zipFile = new ZipFile(array(
        './foo/image-01.jpg',
        './foo/image-02.jpg',
        './foo/image-03.jpg',
    ));
    $zipFile->output('images.zip');

or add them later one by one.

    // Create ZIP archive and add files to the root of the archive
    $zipFile = new ZipFile();
    $zipFile->addFile('./foo/image-01.jpg');
    $zipFile->addFile('./foo/image-02.jpg');
    $zipFile->addFile('./foo/image-03.jpg');
    $zipFile->output('images.zip');


Both methods allows you to specify custom file paths inside the ZIP archive.

    // Create ZIP archive with the list of file paths and new names inside of the archive
    $zipFile = new ZipFile(array(
        './foo/3c42dfce1af8b8d1f0e8d1f9b2655937' => '/birds/eagle.jpg',
        './foo/337346e6fd09d755ebda7f00b7ef03f8' => '/birds/swan.jpg',
        './foo/13b9d3de118ceb08223aea6f244163da' => '/mammals/mouse.jpg',
    ));
        $zipFile->output('images.zip');

or

    // Create ZIP archive and add files with custom paths
    $zipFile = new ZipFile();
    $zipFile->addFile('./foo/3c42dfce1af8b8d1f0e8d1f9b2655937', '/birds/eagle.jpg');
    $zipFile->addFile('./foo/337346e6fd09d755ebda7f00b7ef03f8', '/birds/swan.jpg');
    $zipFile->addFile('./foo/13b9d3de118ceb08223aea6f244163da', '/mammals/mouse.jpg');
    $zipFile->output('images.zip');
