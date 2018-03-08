<?php

namespace Pecee\Pixie;

/**
 * Class NoTableSubQueryTest
 *
 * @package Pecee\Pixie
 */
class NoTableSubQueryTest extends TestCase
{
    public function testRawQuery()
    {
        $subQuery1 = $this->builder->table('mail')->select($this->builder->raw('COUNT(*)'));
        $subQuery2 = $this->builder->table('event_message')->select($this->builder->raw('COUNT(*)'));

        $count = $this->builder->select($this->builder->subQuery($subQuery1, 'row1'), $this->builder->subQuery($subQuery2, 'row2'))->first();

        $this->assertEquals('SELECT (SELECT COUNT(*) FROM `cb_mail`) AS `row1`, (SELECT COUNT(*) FROM `cb_event_message`) AS `row2` LIMIT 1', $count);
    }

}
