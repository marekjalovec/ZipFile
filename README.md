ZipFile
=======

Pass multiple files in one ZIP archive to the output stream with minimum memory footprint


How to use
----------

All files are added to the root of newly created ZIP archive

    <?php
    
        require('ZipFile.php');
    
        $zipFile = new ZipFile(array(
            './foo/image-01.jpg',
            './foo/image-02.jpg',
            './foo/image-03.jpg',
        ));
        $zipFile->output('images.zip');

or

    <?php
    
        require('ZipFile.php');
        
        $zipFile = new ZipFile();
        $zipFile->addFile('./foo/image-01.jpg');
        $zipFile->addFile('./foo/image-02.jpg');
        $zipFile->addFile('./foo/image-03.jpg');
        $zipFile->output('images.zip');
