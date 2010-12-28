<?php
/**
 * Table Definition for queue_item
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Queue_item extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'queue_item';                      // table name
    public $id;                              // int(4)  primary_key not_null
    public $frame;                           // blob not_null
    public $created;                         // datetime()   not_null
    public $claimed;                         // datetime()

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Queue_item',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    /**
     * @param mixed $transports name of a single queue or array of queues to pull from
     *                          If not specified, checks all queues in the system.
     */
    static function top($transports=null) {

        $qi = new Queue_item();
        if ($transports) {
            if (is_array($transports)) {
                // @fixme use safer escaping
                $list = implode("','", array_map(array($qi, 'escape'), $transports));
                $qi->whereAdd("transport in ('$list')");
            } else {
                $qi->transport = $transports;
            }
        }
        $qi->orderBy('created');
        $qi->whereAdd('claimed is null');

        $qi->limit(1);

        $cnt = $qi->find(true);

        if ($cnt) {
            # XXX: potential race condition
            # can we force it to only update if claimed is still null
            # (or old)?
            common_log(LOG_INFO, 'claiming queue item id = ' . $qi->id .
                ' for transport ' . $qi->transport);
            $orig = clone($qi);
            $qi->claimed = common_sql_now();
            $result = $qi->update($orig);
            if ($result) {
                common_log(LOG_INFO, 'claim succeeded.');
                return $qi;
            } else {
                common_log(LOG_INFO, 'claim failed.');
            }
        }
        $qi = null;
        return null;
    }

    /**
     * Release a claimed item.
     */
    function releaseCLaim()
    {
        // DB_DataObject doesn't let us save nulls right now
        $sql = sprintf("UPDATE queue_item SET claimed=NULL WHERE id=%d", $this->id);
        $this->query($sql);

        $this->claimed = null;
        $this->encache();
    }
}
