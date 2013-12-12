<?php

namespace Bowerphp\Installer;

use Bowerphp\Config\ConfigInterface;
use Bowerphp\Package\Package;
use Bowerphp\Package\PackageInterface;
use Bowerphp\Repository\RepositoryInterface;
use Gaufrette\Filesystem;
use Guzzle\Http\ClientInterface;
use Guzzle\Http\Exception\RequestException;

/**
 * Package installation manager.
 *
 */
class Installer implements InstallerInterface
{
    protected
        $filesystem,
        $httpClient,
        $repository,
        $zipArchive,
        $config
    ;

    /**
     * Initializes library installer.
     *
     * @param Filesystem          $filesystem
     * @param ClientInterface     $httpClient
     * @param RepositoryInterface $repository
     * @param ZipArchive          $zipArchive
     * @param ConfigInterface     $config
     */
    public function __construct(Filesystem $filesystem, ClientInterface $httpClient, RepositoryInterface $repository, \ZipArchive $zipArchive, ConfigInterface $config)
    {
        $this->filesystem = $filesystem;
        $this->httpClient = $httpClient;
        $this->repository = $repository;
        $this->zipArchive = $zipArchive;
        $this->config     = $config;
    }

    /**
     * {@inheritDoc}
     */
    public function isInstalled(PackageInterface $package)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function install(PackageInterface $package)
    {
        $package->setTargetDir($this->config->getInstallDir());
        // look for package in bower
        try {
            $request = $this->httpClient->get($this->config->getBasePackagesUrl() . $package->getName());
            $response = $request->send();
        } catch (RequestException $e) {
            throw new \RuntimeException(sprintf('Cannot download package %s (%s).', $package->getName(), $e->getMessage()));
        }
        $decode = json_decode($response->getBody(true), true);
        if (!is_array($decode) || empty($decode['url'])) {
            throw new \RuntimeException(sprintf('Package %s has malformed json or is missing "url".', $package->getName()));
        }

        // open package repository
        $repoUrl = $decode['url'];
        $this->repository->setUrl($repoUrl)->setHttpClient($this->httpClient);
        $bowerJson = $this->repository->getBower();
        $bower = json_decode($bowerJson, true);
        if (!is_array($bower)) {
            throw new \RuntimeException(sprintf('Invalid bower.json found in package %s: %s.', $package->getName(), $bowerJson));
        }
        $packageVersion = $this->repository->findPackage($package->getVersion());
        if (is_null($packageVersion)) {
            throw new \RuntimeException(sprintf('Cannot find package %s version %s.', $package->getName(), $package->getVersion()));
        }
        $package->setRepository($this->repository);

        // get release archive from repository
        $file = $this->repository->getRelease();

        // install files
        $tmpFileName = $this->config->getCacheDir() . '/tmp/' . $package->getName();
        $this->filesystem->write($tmpFileName, $file, true);
        if ($this->zipArchive->open($tmpFileName) !== true) {
            throw new \RuntimeException(sprintf('Unable to open zip file %s.', $tmpFileName));
        }
        $dirName = trim($this->zipArchive->getNameIndex(0), '/');
        for ($i = 1; $i < $this->zipArchive->numFiles; $i++) {
            $stat = $this->zipArchive->statIndex($i);
            if ($stat['size'] > 0) {    // directories have sizes 0
                $fileName = $package->getTargetDir() . '/' . str_replace($dirName, $package->getName(), $stat['name']);
                $fileContent = $this->zipArchive->getStream($stat['name']);
                $this->filesystem->write($fileName, $fileContent, true);
            }
        }
        $this->zipArchive->close();
        // check for dependencies
        if (!empty($bower['dependencies'])) {
            foreach ($bower['dependencies'] as $name => $version) {
                $depPackage = new Package($name, $version);
                $this->install($depPackage);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function update(PackageInterface $package)
    {
        // look for installed package
        $bowerFile = $this->config->getInstallDir() . '/' . $package->getName() . '/bower.json';
        if (!$this->filesystem->has($bowerFile)) {
            $bowerFile = $this->config->getInstallDir() . '/' . $package->getName() . '/package.json';
            if (!$this->filesystem->has($bowerFile)) {
                throw new \RuntimeException(sprintf('Could not find bower.json nor package.json for package %s.', $package->getName()));
            }
        }
        $bowerJson = $this->filesystem->read($bowerFile);
        $bower = json_decode($bowerJson, true);
        if (is_null($bower)) {
            throw new \RuntimeException(sprintf('Could not find bower.json for package %s.', $package->getName()));
        }
        $version = $bower['version'];

        // match installed package version with $package version
        if ($version == $package->getVersion()) {
            // if version is fully matching, OK
            return;
        }

        $package->setTargetDir($this->config->getInstallDir());

        // look for package in bower
        try {
            $request = $this->httpClient->get($this->config->getBasePackagesUrl() . $package->getName());
            $response = $request->send();
        } catch (RequestException $e) {
            throw new \RuntimeException(sprintf('Cannot download package %s (%s).', $package->getName(), $e->getMessage()));
        }
        $decode = json_decode($response->getBody(true), true);
        if (!is_array($decode) || empty($decode['url'])) {
            throw new \RuntimeException(sprintf('Package %s has malformed json or is missing "url".', $package->getName()));
        }

        // open package repository
        $repoUrl = $decode['url'];
        $this->repository->setUrl($repoUrl)->setHttpClient($this->httpClient);
        $bowerJson = $this->repository->getBower();
        $bower = json_decode($bowerJson, true);
        if (!is_array($bower)) {
            throw new \RuntimeException(sprintf('Invalid bower.json found in package %s: %s.', $package->getName(), $bowerJson));
        }
        $packageVersion = $this->repository->findPackage($package->getVersion());
        if (is_null($packageVersion)) {
            throw new \RuntimeException(sprintf('Cannot find package %s version %s.', $package->getName(), $package->getVersion()));
        }
        $package->setRepository($this->repository);

        // get release archive from repository
        $file = $this->repository->getRelease();

        // match installed package version with lastest available version
        if ($packageVersion == $version) {
            // if version is fully matching, OK
            return;
        }

        // install files
        $tmpFileName = $this->config->getCacheDir() . '/tmp/' . $package->getName();
        $this->filesystem->write($tmpFileName, $file, true);
        if ($this->zipArchive->open($tmpFileName) !== true) {
            throw new \RuntimeException(sprintf('Unable to open zip file %s.', $tmpFileName));
        }
        $dirName = trim($this->zipArchive->getNameIndex(0), '/');
        for ($i = 1; $i < $this->zipArchive->numFiles; $i++) {
            $stat = $this->zipArchive->statIndex($i);
            if ($stat['size'] > 0) {    // directories have sizes 0
                $fileName = $package->getTargetDir() . '/' . str_replace($dirName, $package->getName(), $stat['name']);
                $fileContent = $this->zipArchive->getStream($stat['name']);
                $this->filesystem->write($fileName, $fileContent, true);
            }
        }
        $this->zipArchive->close();

        // check for dependencies
        if (!empty($bower['dependencies'])) {
            foreach ($bower['dependencies'] as $name => $version) {
                $depPackage = new Package($name, $version);
                $bowerFile = $this->config->getInstallDir() . '/' . $depPackage->getName() . '/bower.json';
                if (!$this->filesystem->has($bowerFile)) {
                    $this->install($depPackage);
                } else {
                    $this->update($depPackage);
                }
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall(PackageInterface $package)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getInstallPath(PackageInterface $package)
    {
    }

    protected function getPackageBasePath(PackageInterface $package)
    {
    }
}
