    /**
     * 数组转换成树形结构
     *
     * @param  array $data          数组数据
     * @param  string $idName       主要的id
     * @param  string $parentIdName 父级id
     * @param  string $childName    子级名称
     * @param  string $sortName     排序字段
     * @return array
     */
    function toTree(array $data, $idName = 'id', $parentIdName = 'parent_id', $childName = 'children', $sortName = 'sort')
    {
        $list = array();

        foreach($data as $val) {
            $val[$childName]  = array();
            $list[$val[$idName]] = $val;
        }

        $result = array();

        foreach($list as $key => &$val) {
            if($val[$parentIdName] == 0) {
                //手动排序
                if(count($result) > 0) {
                    $inserted = false;
                    foreach($result as $k => $v) {
                        if($v[$sortName] > $val[$sortName]) {
                            array_splice($result, $k, 0, array(&$val));
                            $inserted = true;
                            break;
                        }
                    }
                    if(!$inserted) $result[] = &$val;
                } else {
                    $result[] = &$val;
                }
            } else {
                $target = &$list[$val[$parentIdName]][$childName];

                if(count($target) > 0) {
                    $inserted = false;
                    foreach($target as $k => $v) {
                        if($v[$sortName] > $val[$sortName]) {
                            $inserted = true;
                            array_splice($target, $k, 0, array(&$val));
                            break;
                        }
                    }
                    if(!$inserted) $target[] = &$val;
                } else {
                    $target[] = &$val;
                }
            }
        }

        return $result;
    }
