<?php

namespace KnpU\ActivityRunner;

use Doctrine\Common\Collections\Collection;
use KnpU\ActivityRunner\ActivityInterface;
use KnpU\ActivityRunner\Assert\ClassLoader;
use KnpU\ActivityRunner\Exception\FileNotFoundException;
use KnpU\ActivityRunner\Exception\SkeletonNotFoundException;
use KnpU\ActivityRunner\Exception\UnexpectedTypeException;
use KnpU\ActivityRunner\Assert\AssertSuite;

/**
 * @author Kristen Gilden <kristen.gilden@knplabs.com>
 */
class Activity implements ActivityInterface
{
    /**
     * @var array
     */
    private $context;

    /**
     * @var ClassLoader
     */
    private $classLoader;

    /**
     * Path to a context file.
     *
     * @var string
     */
    private $contextPath;

    /**
     * Logical name of the file to be considered as the entry point.
     *
     * @var string
     */
    private $entryPoint;

    /**
     * @var Collection
     */
    private $inputFiles;

    /**
     * @var string
     */
    private $question;

    /**
     * @var array
     */
    private $skeletonPaths;

    /**
     * @var AssertSuite
     */
    private $suite;

    /**
     * @var string
     */
    private $suiteSource;

    /**
     * @var string
     */
    private $workerName;

    /**
     * @param ClassLoader $classLoader
     */
    public function __construct(ClassLoader $classLoader)
    {
        $this->classLoader = $classLoader;
    }

    /**
     * Specifies the location of skeleton files.
     *
     * @param array $filePaths  List of paths to skeleton files
     */
    public function setSkeletons(array $filePaths)
    {
        if (empty($filePaths)) {
            throw new \InvalidArgumentException('You must provide at least 1 skeleton file.');
        }

        foreach ($filePaths as $filePath) {
            if (!is_file($filePath)) {
                throw new FileNotFoundException($filePath);
            }
        }

        $this->skeletonPaths = $filePaths;
    }

    /**
     * @param string $entryPoint
     *
     * @throws \LogicException if skeletons have not been set before
     * @throws \RuntimeException if the entry point file is not one of the skeleton files
     */
    public function setEntryPoint($entryPoint)
    {
        if (is_null($this->skeletonPaths)) {
            throw new \LogicException('You must first set the skeleton files.');
        }

        if (!isset($this->skeletonPaths[$entryPoint])) {
            throw new \RuntimeException(sprintf('No file named `%s` found, available files: `%s`', $entryPoint, implode('`, `', array_keys($this->skeletonPaths))));
        }

        $this->entryPoint = $entryPoint;
    }

    /**
     * @return string
     */
    public function getEntryPoint()
    {
        return $this->entryPoint;
    }

    /**
     * @param string $input
     *
     * @throws \RuntimeException if the logical file paths of skeleton files do not match with user input file paths
     */
    public function setInputFiles(Collection $files)
    {
        $allowedPaths = array_keys($this->skeletonPaths);
        $actualPaths  = $files->getKeys();

        if (($diff = array_diff($allowedPaths, $actualPaths)) ||
            ($diff = array_diff($actualPaths, $allowedPaths))
        ) {
            throw new \RuntimeException(sprintf("User input does not match skeleton files - key diff: `%s`", implode('`, `', $diff)));
        }

        $this->inputFiles = $files;
    }

    /**
     * {@inheritDoc}
     */
    public function getInputFiles()
    {
        return $this->inputFiles;
    }

    /**
     * Sets the path to the context file. For the sake of performance the file
     * is only validated as the context is asked from the Activity.
     *
     * @param string $filePath
     *
     * @throws FileNotFoundException if the file was not found
     */
    public function setContext($filePath)
    {
        if (!is_file($filePath)) {
            throw new FileNotFoundException($filePath);
        }

        $this->contextPath = $filePath;
        $this->context     = null;
    }

    /**
     * {@inheritDoc}
     *
     * @throws \LogicException if the context path is not set yet
     * @throws UnexpectedTypeException if the context file does not return an array
     */
    public function getContext()
    {
        if ($this->context) {
            return $this->context;
        }

        if (!$this->contextPath) {
            throw new \LogicException('You must set the context path prior to asking for context.');
        }

        $context = require($this->contextPath);

        if (!is_array($context)) {
            throw new UnexpectedTypeException($context, 'array');
        }

        $this->context = $context;

        return $this->context;
    }

    /**
     * @param string $question
     */
    public function setQuestion($question)
    {
        $this->question = $question;
    }

    /**
     * {@inheritDoc}
     */
    public function getQuestion()
    {
        return $this->question;
    }

    /**
     * {@inheritDoc}
     */
    public function getSkeletonContents($logicalName)
    {
        if (!isset($this->skeletonPaths[$logicalName])) {
            throw new SkeletonNotFoundException($logicalName, array_keys($this->skeletonPaths));
        }

        return file_get_contents($this->skeletonPaths[$logicalName]);
    }

    /**
     * Sets the suite source. The source can either be a FQCN or absolute path
     * to the class file. In both cases the classes must inherit from
     * `KnpU\ActivityRunner\Assert\AssertSuite`.
     *
     * @param string $suiteSource  FQCN of the suite class or absolute path to class
     */
    public function setSuiteSource($suiteSource)
    {
        $this->suiteSource = $suiteSource;

        $this->suite = null;
    }

    /**
     * {@inheritDoc}
     */
    public function getSuite()
    {
        if (!$this->suite) {
            if (!$this->suiteSource) {
                throw new \LogicException('You must set the suite class before attempting to get a suite.');
            }

            $suiteClass = $this->classLoader->load($this->suiteSource);

            $suite = new $suiteClass();

            if (!($suite instanceof AssertSuite)) {
                throw new UnexpectedTypeException($suiteClass, 'KnpU\\ActivityRunner\\Assert\\AssertSuite');
            }

            $this->suite = $suite;
        }

        return $this->suite;
    }

    /**
     * Sets the worker name, which should run this activity.
     *
     * @param string $workerName
     */
    public function setWorkerName($workerName)
    {
        $this->workerName = $workerName;
    }

    /**
     * {@inheritDoc}
     */
    public function getWorkerName()
    {
        return $this->workerName;
    }
}
