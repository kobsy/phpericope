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
      self::$_regexp = "/$book_pattern\\.?\\s*($reference_pattern)/i";
    }
    return self::$_regexp;
  }

  public static function normalizations() {
    if(!isset(self::$_normalizations)) {
      $letters = self::letters();
      self::$_normalizations = array(
        array('pattern' => '/(\\d+)[".](\\d+)/', 'replacement' => '$1:$2'),
        array('pattern' => '/[–—]/', 'replacement' => '-'),
        array('pattern' => "/[^0-9,:;\\-–—$letters]/", '')
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
  private static $_fragment_regexp;


  private function group_array_into_ranges($verses) {
    if(is_null($verses) || count($verses) == 0) return array();

    $parsed_verses = array();
    foreach($verses as $verse) {
      $parsed_verse = Verse::parse($verse);
      if(!is_null($parsed_verse)) $parsed_verses[] = $parsed_verse;
    }
    $verse_cmp = function($a, $b) {
      // Don't just use the spaceship operator for compatibility with PHP 5!
      if($a->number() == $b->number()) return 0;
      return ($a->number() < $b->number()) ? -1 : 1;
    };
    usort($parsed_verses, $verse_cmp);

    $ranges = array();
    $range_begin = array_shift($parsed_verses);
    $range_end = $range_begin;

    while($verse = array_shift($parsed_verses)) {
      if($verse->number() > $range_end->next()) {
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
    $verse = "$number[$letters]?";
    $chapter_verse_separator = '\s*[:"\.]\s*';
    $list_or_range_separator = '\s*[\-–—,;]\s*';
    $chapter_and_verse = "(?:$number$chapter_verse_separator)?$verse";
    $chapter_and_verse_or_letter = "(?:$chapter_and_verse|[$letters])";
    return "$chapter_and_verse(?:$list_or_range_separator$chapter_and_verse_or_letter)*";
  }

  private static function letters() {
    return implode(range('a', self::max_letter));
  }

}

?>
