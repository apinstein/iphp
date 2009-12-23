<?php

$spec = PackageSpec::create(array(PackageSpec::OPT_BASEDIR => dirname(__FILE__)))
            ->setName('iphp')
            ->setChannel('pear.nimblize.com')
            ->setSummary('PHP Shell')
            ->setDescription('An interactive PHP Shell (or Console, or REPL).')
            ->setReleaseVersion('1.0.0')
            ->setReleaseStability('stable')
            ->setApiVersion('1.0.0')
            ->setApiStability('stable')
            ->setLicense(PackageSpec::LICENSE_MIT)
            ->setNotes('First release of iphp.')
            ->addMaintainer('lead', 'Alan Pinstein', 'apinstein', 'apinstein@mac.com')
            ->addGitFiles()
            ->addExcludeFiles('.gitignore')
            ->addExecutable('iphp')
            ;
