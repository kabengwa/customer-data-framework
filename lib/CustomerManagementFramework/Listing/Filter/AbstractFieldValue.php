<?php

namespace CustomerManagementFramework\Listing\Filter;

use CustomerManagementFramework\Listing\OnCreateQueryFilterInterface;
use Pimcore\Db;
use Pimcore\Model\Object\Listing as CoreListing;

abstract class AbstractFieldValue extends AbstractFilter implements OnCreateQueryFilterInterface
{
    /**
     * @var array
     */
    protected $fields = [];

    /**
     * @var string
     */
    protected $value;

    /**
     * @var bool
     */
    protected $inverse = false;

    /**
     * @param string|array $fields
     * @param string $value
     * @param bool $inverse
     */
    public function __construct($fields, $value, $inverse = false)
    {
        if (is_array($fields)) {
            $this->fields = $fields;
        } else {
            if (!empty($fields)) {
                $this->fields[] = $fields;
            }
        }

        if (empty($this->fields)) {
            throw new \InvalidArgumentException('Field filter needs at least one field to operate on');
        }

        $this->value   = trim($value);
        $this->inverse = (bool)$inverse;
    }

    /**
     * @param string $value
     * @return string
     */
    protected function processValue($value)
    {
        return $value;
    }

    /**
     * Apply filter directly to query
     *
     * @param CoreListing\Concrete|CoreListing\Dao $listing
     * @param \Zend_Db_Select $query
     */
    public function applyOnCreateQuery(CoreListing\Concrete $listing, \Zend_Db_Select $query)
    {
        if (empty($this->value)) {
            return;
        }

        $value     = $this->processValue($this->value);
        $tableName = $this->getTableName($listing->getClassId());

        // we just have one field so match -> no sub-query needed
        if (count($this->fields) === 1) {
            $this->applyFieldCondition($this->fields[0], $value, $tableName, $query);
        } else {
            // build a sub-query to assemble where condition
            $subQuery = Db::getConnection()->select();
            $operator = $this->getBooleanFieldOperator();

            foreach ($this->fields as $field) {
                $this->applyFieldCondition($field, $value, $tableName, $subQuery, $operator);
            }

            // add assembled sub-query where condition to our main query
            $query->where(implode(' ', $subQuery->getPart(\Zend_Db_Select::WHERE)));
        }
    }

    /**
     * Apply field condition to query/sub-query
     *
     * @param $field
     * @param $value
     * @param $tableName
     * @param \Zend_Db_Select $query
     * @param string $operator
     */
    protected function applyFieldCondition($field, $value, $tableName, \Zend_Db_Select $query, $operator = \Zend_Db_Select::SQL_AND)
    {
        $condition = sprintf(
            '`%s`.`%s` %s ?',
            $tableName,
            $field,
            $this->getComparisonOperator()
        );

        if ($operator === \Zend_Db_Select::SQL_OR) {
            $query->orWhere($condition, $value);
        } else {
            $query->where($condition, $value);
        }
    }

    /**
     * Get operator to join field conditions on. Uses AND for inverse searches, OR for normal ones.
     *
     * @return string
     */
    protected function getBooleanFieldOperator()
    {
        if ($this->inverse) {
            return \Zend_Db_Select::SQL_AND;
        } else {
            return \Zend_Db_Select::SQL_OR;
        }
    }

    /**
     * @return string
     */
    abstract protected function getComparisonOperator();
}