<?php

namespace Bowerphp\Package;

use Bowerphp\Repository\RepositoryInterface;

/**
 * Defines the essential information a package has that is used during solving/installation
 *
 */
interface PackageInterface
{
    /**
     * Returns the package's name without version info, thus not a unique identifier
     *
     * @return string package name
     */
    public function getName();

    /**
     * Returns the package targetDir property
     *
     * @return string The package targetDir
     */
    public function getTargetDir();

    /**
     * Set the package targetDir property
     *
     * @param string $targetDir
     */
    public function setTargetDir($targetDir);

    /**
     * Returns the version of this package
     *
     * @return string version
     */
    public function getVersion();

    /**
     * Set the version of this package
     *
     * @param string version
     */
    public function setVersion($version);

    /**
     * Returns a set of links to packages which need to be installed before
     * this package can be installed
     *
     * @return array An array of package links defining required packages
     */
    public function getRequires();

    /**
     * Stores a reference to the repository that owns the package
     *
     * @param RepositoryInterface $repository
     */
    public function setRepository(RepositoryInterface $repository);

    /**
     * Returns a reference to the repository that owns the package
     *
     * @return RepositoryInterface
     */
    public function getRepository();

    /**
     * Converts the package into a readable and unique string
     *
     * @return string
     */
    public function __toString();
}
