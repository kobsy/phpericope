<?php
namespace PHPericope;

require_once 'pericope/data.php';
require_once 'pericope/parsing.php';
require_once 'pericope/Verse.php';
require_once 'pericope/Range.php';

class Pericope {

  public static $max_letter = 'd';

  public $book;
  public $original_string;
  public $ranges;

  public function __construct($arg) {
    if(is_string($arg)) {
      $attributes = match_one($arg);
      if(is_null($attributes)) throw new InvalidArgumentException("no pericope found in $arg");

      $this->original_string = $attributes['original_string'];
      $this->book = $attributes['book'];
      $this->ranges = $attributes['ranges'];
    } elseif(array_key_exists('book', $arg)) {
      $this->original_string = $arg['original_string'];
      $this->book = $arg['book'];
      $this->ranges = $arg['ranges'];
    } else {
      $this->ranges = $this->group_array_into_ranges($arg);
      $this->book = $this->ranges[0]->begin->book;
    }

    if(is_null($this->book)) throw new InvalidArgumentException("must specify book");
  }

  public function book_has_chapters() {
    return $this->book_chapter_count() > 1;
  }

  public function book_name() {
    if(!isset($this->_book_name)) $this->_book_name = BOOK_NAMES[$this->book];
    return $this->_book_name;
  }

  public function book_chapter_count() {
    if(!isset($this->_book_chapter_count)) $this->_book_chapter_count = BOOK_CHAPTER_COUNTS[$this->book];
    return $this->_book_chapter_count;
  }

  public function to_string($options=null) {
    return $this->book_name() . ' ' . $this->well_formatted_reference($options);
  }

  public function __toString() {
    $this->to_string();
  }

  public function intersects($other) {
    if(!($other instanceof Pericope)) return false;
    if($this->book != $other->book) return false;

    foreach($this->ranges as $self_range) {
      foreach($other->ranges as $other_range) {
        if($self_range->end->versecmp($other_range->begin) > -1 && $self_range->begin->versecmp($other_range->end) < 1) return true;
      }
    }

    return false;
  }

  public function each_verse() {
    foreach($this->ranges as $range) {
      foreach($range->each_verse() as $verse) {
        yield $verse;
      }
    }
  }

  public function to_array() {
    $array = array();
    foreach($this->each_verse() as $verse) { $array[] = $verse; }
    return $array;
  }

  public static function has_chapters($book) {
    return BOOK_CHAPTER_COUNTS[$book] > 1;
  }

  public static function get_max_verse($book, $chapter) {
    $id = ($book * 1000000) + ($chapter * 1000);
    return CHAPTER_VERSE_COUNTS[$id];
  }

  public static function get_max_chapter($book) {
    return BOOK_CHAPTER_COUNTS[$book];
  }

  public static function regexp() {
    if(!isset(self::$_regexp)) {
      $book_pattern = self::book_pattern();
      $reference_pattern = self::reference_pattern();
      self::$_regexp = "/$book_pattern\\.?\\s*($reference_pattern)/iu";
    }
    return self::$_regexp;
  }

  public static function normalizations() {
    if(!isset(self::$_normalizations)) {
      $letters = self::letters();
      self::$_normalizations = array(
        array('pattern' => '/(\\d+)[".](\\d+)/', 'replacement' => '$1:$2'),
        array('pattern' => '/[–—]/u', 'replacement' => '-'),
        array('pattern' => "/[^0-9,:;\\-–—$letters]/u", 'replacement' => '')
      );
    }
    return self::$_normalizations;
  }

  public static function letter_regexp() {
    return '/[' . self::letters() . ']$/';
  }

  public static function fragment_regexp() {
    if(!isset(self::$_fragment_regexp)) {
      $letters = self::letters();
      self::$_fragment_regexp = "/^(?:(?<chapter>\\d{1,3}):)?(?<verse>\\d{1,3})?(?<letter>[$letters])?$/";
    }
    return self::$_fragment_regexp;
  }


  private static $_regexp;
  private static $_book_pattern;
  private static $_normalizations;
  private static $_fragment_regexp;

  private $_book_name;
  private $_book_chapter_count;

  private function well_formatted_reference($options) {
    if(is_null($options)) $options = array();

    $verse_range_separator = array_key_exists('verse_range_separator', $options) ? $options['verse_range_separator'] : "–"; // en-dash
    $chapter_range_separator = array_key_exists('chapter_range_separator', $options) ? $options['chapter_range_separator'] : "—"; // em-dash
    $verse_list_separator = array_key_exists('verse_list_separator', $options) ? $options['verse_list_separator'] : ", ";
    $chapter_list_separator = array_key_exists('chapter_list_separator', $options) ? $options['chapter_list_separator'] : "; ";
    $always_print_verse_range = array_key_exists('always_print_verse_range', $options) ? $options['always_print_verse_range'] : false;
    if(!$this->book_has_chapters()) $always_print_verse_range = true;

    $recent_chapter = null; // e.g., in 12:1-8, remember that 12 is the chapter when we parse the 8
    if(!$this->book_has_chapters()) $recent_chapter = 1;
    $recent_verse = null;

    $output = "";
    foreach($this->ranges as $i => $range) {
      if($i > 0) {
        if($recent_chapter == $range->begin->chapter) {
          $output .= $verse_list_separator;
        } else {
          $output .= $chapter_list_separator;
        }
      }

      $last_verse = self::get_max_verse($this->book, $range->end->chapter);
      if(!$always_print_verse_range && $range->begin->verse == 1 && $range->begin->is_whole() && ($range->end->verse > $last_verse || $range->end->is_whole() && $range->end->verse == $last_verse)) {
        $output .= $range->begin->chapter;
        if($range->end->chapter > $range->begin->chapter) $output .= $chapter_range_separator . $range->end->chapter;
      } else {
        if($range->begin->is_partial() && $range->begin->verse == $recent_verse) {
          $output .= $range->begin->letter;
        } else {
          $output .= $range->begin->to_string($recent_chapter != $range->begin->chapter);
        }

        if($range->begin->versecmp($range->end) != 0) {
          if($range->begin->chapter == $range->end->chapter) {
            $output .= $verse_range_separator . $range->end->to_string();
          } else {
            $output .= $chapter_range_separator . $range->end->to_string(true);
          }
        }

        $recent_chapter = $range->end->chapter;
        if($range->end->is_partial()) $recent_verse = $range->end->verse;
      }
    }

    return $output;
  }

  private function group_array_into_ranges($verses) {
    if(is_null($verses) || count($verses) == 0) return array();

    $parsed_verses = array();
    foreach($verses as $verse) {
      $parsed_verse = Verse::parse($verse);
      if(!is_null($parsed_verse)) $parsed_verses[] = $parsed_verse;
    }
    $verse_cmp = function($a, $b) { return $a->versecmp($b); };
    usort($parsed_verses, $verse_cmp);

    $ranges = array();
    $range_begin = array_shift($parsed_verses);
    $range_end = $range_begin;

    while($verse = array_shift($parsed_verses)) {
      if($verse->versecmp($range_end->next()) > 0) {
        $ranges[] = new Range($range_begin, $range_end);
        $range_begin = $range_end = $verse;
      } else {
        $range_end = $verse;
      }
    }

    $ranges[] = new Range($range_begin, $range_end);
    return $ranges;
  }


  private static function book_pattern() {
    if(!isset(self::$_book_pattern)) self::$_book_pattern = preg_replace('/[ \n]/', '', BOOK_PATTERN);
    return self::$_book_pattern;
  }

  private static function reference_pattern() {
    $number = '\d{1,3}';
    $letters = self::letters();
    $verse = "$number" . "[$letters]?";
    $chapter_verse_separator = '\s*[:"\.]\s*';
    $list_or_range_separator = '\s*[\-–—,;]\s*';
    $chapter_and_verse = "(?:$number$chapter_verse_separator)?$verse";
    $chapter_and_verse_or_letter = "(?:$chapter_and_verse|[$letters])";
    return "$chapter_and_verse(?:$list_or_range_separator$chapter_and_verse_or_letter)*";
  }

  private static function letters() {
    return implode(range('a', self::$max_letter));
  }

}

?>
