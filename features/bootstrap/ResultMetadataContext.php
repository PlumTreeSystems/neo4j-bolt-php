<?php

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;

use GraphAware\Bolt\Driver;
use GraphAware\Common\Result\SummaryInterface;
use GraphAware\Common\Cypher\StatementInterface;
use PHPUnit_Framework_Assert as Assert;

/**
 * Defines application features from the specific context.
 */
class ResultMetadataContext implements Context, SnippetAcceptingContext
{
    /**
     * @var |GraphAware\Bolt\Driver
     */
    protected $driver;

    protected $result;

    protected $summary;

    protected $statement;

    protected $statistics;

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct()
    {
    }

    /**
     * @Given there is a driver configured with the :arg1 uri
     */
    public function thereIsADriverConfiguredWithTheUri($arg1)
    {
        $this->driver = new Driver("localhost", 7687);
    }

    /**
     * @When I run a statement
     */
    public function iRunAStatement()
    {
        $session = $this->driver->getSession();
        $this->result = $session->run("CREATE (n:Node) RETURN n");
    }

    /**
     * @When I summarize it
     */
    public function iSummarizeIt()
    {
        $this->summary = $this->result->summarize();
    }

    /**
     * @Then I should get a Result Summary back
     */
    public function iShouldGetAResultSummaryBack()
    {
        Assert::assertInstanceOf(SummaryInterface::class, $this->summary);
    }

    /**
     * @When I request a statement from it
     */
    public function iRequestAStatementFromIt()
    {
        $this->statement = $this->summary->statement();
    }

    /**
     * @Then I should get a Statement back
     */
    public function iShouldGetAStatementBack()
    {
        Assert::assertInstanceOf(StatementInterface::class, $this->statement);
    }

    /**
     * @When I run a statement with text :arg1
     */
    public function iRunAStatementWithText($arg1)
    {
        $session = $this->driver->getSession();
        $this->result = $session->run($arg1);
    }

    /**
     * @Then I can request the statement text and the text should be :arg1
     */
    public function iCanRequestTheStatementTextAndTheTextShouldBe($arg1)
    {
        Assert::assertEquals($arg1, $this->statement->getQuery());
    }

    /**
     * @Then the statement parameters should be a map
     */
    public function theStatementParametersShouldBeAMap()
    {
        Assert::assertInternalType("array", $this->statement->getParameters());
    }

    /**
     * @When I request the update statistics
     */
    public function iRequestTheUpdateStatistics()
    {
        $this->statistics = $this->summary->updateStatistics();
    }

    /**
     * @Then I should get the UpdateStatistics back
     */
    public function iShouldGetTheUpdatestatisticsBack()
    {
        Assert::assertInternalType("array", $this->statistics);
    }
}