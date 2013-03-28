<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Driver\SQLAnywhere;

use Doctrine\DBAL\Driver\Connection;

/**
 * SAP Sybase SQL Anywhere implementation of the Connection interface.
 *
 * @author Steve Müller <st.mueller@dzh-online.de>
 * @link   www.doctrine-project.org
 * @since  2.5
 */
class SQLAnywhereConnection implements Connection
{
    /**
     * @var resource The SQL Anywhere connection resource.
     */
    private $conn;

    /**
     * Constructor.
     *
     * Connects to database with given connection string.
     *
     * @param string  $dsn        The connection string.
     * @param boolean $persistent Whether or not to establish a persistent connection.
     *
     * @throws SQLAnywhereException
     */
    public function __construct($dsn, $persistent = false)
    {
        $this->conn = $persistent ? @sasql_pconnect($dsn) : @sasql_connect($dsn);

        if ( ! is_resource($this->conn) || get_resource_type($this->conn) != 'SQLAnywhere connection') {
            throw SQLAnywhereException::fromSQLAnywhereError();
        }

        /**
         * Disable PHP warnings on error
         */
        if ( ! sasql_set_option($this->conn, 'verbose_errors', false)) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->conn);
        }

        /**
         * Enable auto committing by default
         */
        if ( ! sasql_set_option($this->conn, 'auto_commit', 'on')) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->conn);
        }

        /**
         * Enable exact, non-approximated row count retrieval
         */
        if ( ! sasql_set_option($this->conn, 'row_counts', true)) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->conn);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws SQLAnywhereException
     */
    public function beginTransaction()
    {
        if ( ! sasql_set_option($this->conn, 'auto_commit', 'off')) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->conn);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @throws SQLAnywhereException
     */
    public function commit()
    {
        if ( ! sasql_commit($this->conn)) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->conn);
        }

        $this->endTransaction();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function errorCode()
    {
        return sasql_errorcode($this->conn);
    }

    /**
     * {@inheritdoc}
     */
    public function errorInfo()
    {
        return sasql_error($this->conn);
    }

    /**
     * {@inheritdoc}
     */
    public function exec($statement)
    {
        $stmt = $this->prepare($statement);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId($name = null)
    {
        if ($name === null) {
            return sasql_insert_id($this->conn);
        }

        return $this->query('SELECT ' . $name . '.CURRVAL')->fetchColumn();
    }

    /**
     * {@inheritdoc}
     */
    public function prepare($prepareString)
    {
        return new SQLAnywhereStatement($this->conn, $prepareString);
    }

    /**
     * {@inheritdoc}
     */
    public function query()
    {
        $args = func_get_args();
        $stmt = $this->prepare($args[0]);
        $stmt->execute();

        return $stmt;
    }

    /**
     * {@inheritdoc}
     */
    public function quote($input, $type = \PDO::PARAM_STR)
    {
        if (is_int($input) || is_float($input)) {
            return $input;
        }

        return "'" . sasql_escape_string($this->conn, $input) . "'";
    }

    /**
     * {@inheritdoc}
     *
     * @throws SQLAnywhereException
     */
    public function rollBack()
    {
        if ( ! sasql_rollback($this->conn)) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->conn);
        }

        $this->endTransaction();

        return true;
    }

    /**
     * Ends transactional mode and enables auto commit again.
     *
     * @throws SQLAnywhereException
     *
     * @return boolean Whether or not ending transactional mode succeeded.
     */
    private function endTransaction()
    {
        if ( ! sasql_set_option($this->conn, 'auto_commit', 'on')) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->conn);
        }

        return true;
    }
}