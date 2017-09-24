<?php

abstract class dict {

  static function get($keyword){
    $dict = @json_decode(@file_get_contents(ZSLIST_DICT));
    $val = $dict->{$keyword};
    foreach(['ZSLIST_LISTNAME','ZSLIST_HOST']as $k)
      $val = str_replace($k, constant($k), $val);
    return $val;
  }

}
