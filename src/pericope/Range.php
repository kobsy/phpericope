<?php
namespace PHPericope;

class Range {
  public $begin;
  public $end;

  public function __construct($first, $last) {
    $this->begin = $first;
    $this->end = $last;
  }

  public function each_verse() {
    if($this->begin == $this->end) {
      yield $this->begin;
      return;
    }

    $current = $this->begin;
    $last_verse = $this->end->whole();
    while($current->versecmp($last_verse) < 0) {
      yield $current;
      $current = $current->next();
    }

    if($this->end->is_partial()) {
      foreach(range('a', $this->end->letter) as $letter) {
        yield new Verse($this->end->book, $this->end->chapter, $this->end->verse, $letter);
      }
    } else {
      yield $this->end;
    }
  }

  public function __toString() {
    return $this->begin->to_id() . '..' . $this->end->to_id();
  }
}

?>
