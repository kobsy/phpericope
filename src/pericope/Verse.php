<?php
namespace PHPericope\Pericope;

class Verse {

  public $book;
  public $chaper;
  public $verse;
  public $letter;

  function __construct($book, $chapter, $verse, $letter=null) {
    $this->book = $book;
    $this->chapter = $chapter;
    $this->verse = $verse;
    $this->letter = $letter;

    if($this->book < 1 || $this->book > 66) throw new InvalidArgumentException("$this->book is not a valid book");
    if($this->chapter < 1 || $this->chapter > Pericope::get_max_chapter($this->book)) throw new InvalidArgumentException("$this->chapter is not a valid chapter");
    if($this->verse < 1 || $this->verse > Pericope::get_max_verse($this->book, $this->chapter)) throw new InvalidArgumentException("$this->verse is not a valid verse");
  }

  public static function parse($input) {
    if(is_null($input)) return null;
    $id = intval($input);
    $book = $id / 1000000;             // the book is everything left of the least significant 6 digits
    $chapter = ($id % 1000000) / 1000; // the chapter is the 3rd through 6th most significant digits
    $verse = $id % 1000;               // the verse is the 3 least significant digits
    $letter = null;
    if(is_string($input)) {
      if(preg_match(Pericope::letter_regexp(), $input, $matches)) {
        $letter = $matches[0];
      }
    }

    return new static($book, $chapter, $verse, $letter);
  }

  public function number() {
    return $this->book * 1000000 + $this->chapter * 1000 + verse;
  }

  public function to_id() {
    return $this->number() . $this->letter;
  }

  public function to_string($with_chapter = false) {
    return $with_chapter ? $this->chapter . ':' . $this->verse . $this->letter : $this->verse . $this.letter;
  }

  public function __toString() {
    return $this-> to_string(true);
  }

  public function is_partial() {
    return $this->letter !== null;
  }

  public function is_whole() {
    return $this->letter === null;
  }

  public function whole() {
    if($this->is_whole()) return $this;
    return new static($this->book, $this->chapter, $this->verse);
  }

  public function next() {
    if($this->is_partial() && ($next_letter = chr(ord($this->letter) + 1)) <= Pericope::max_letter) {
      return new static($this->book, $this->chapter, $this->verse, $next_letter);
    }

    $next_verse = $this->verse + 1;
    if($next_verse > Pericope::get_max_verse($this->book, $this->chapter)) {
      $next_chapter = $this->chapter + 1;
      if($next_chapter > Pericope::get_max_chapter($this->book)) return null;
      return new static($this->book, $next_chapter, 1);
    } else {
      return new static($this->book, $this->chapter, $next_verse);
    }
  }
}

?>
