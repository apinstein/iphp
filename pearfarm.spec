<?php

$spec = Pearfarm_PackageSpec::create(array(Pearfarm_PackageSpec::OPT_BASEDIR => dirname(__FILE__)))
            ->setName('iphp')
            ->setChannel('apinstein.pearfarm.org')
            ->setSummary('PHP Shell')
            ->setDescription('An interactive PHP Shell (or Console, or REPL).')
            ->setReleaseVersion('1.0.0')
            ->setReleaseStability('stable')
            ->setApiVersion('1.0.0')
            ->setApiStability('stable')
            ->setLicense(Pearfarm_PackageSpec::LICENSE_MIT)
            ->setNotes('First release of iphp.')
            ->addMaintainer('lead', 'Alan Pinstein', 'apinstein', 'apinstein@mac.com')
            ->addGitFiles()
            ->addExcludeFiles('.gitignore')
            ->addExecutable('iphp')
            ;
