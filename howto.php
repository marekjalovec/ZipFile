<?php

	require('ZipFile.php');

//	$zipFile = new ZipFile();
//	$zipFile->addFile('./foo/image-01.jpg');
//	$zipFile->addFile('./foo/image-02.jpg');
//	$zipFile->addFile('./foo/image-03.jpg');
//	$zipFile->output('images.zip');

	$zipFile = new ZipFile(array(
		'./foo/image-01.jpg',
		'./foo/image-02.jpg',
		'./foo/image-03.jpg',
	));
	$zipFile->output('images.zip');
