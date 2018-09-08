<?php
namespace PHPericope;

require_once 'pericope/data.php';
require_once 'pericope/Verse.php';
require_once 'pericope/Range.php';

class Pericope {

  public static $max_letter = 'd';

  private static $_regexp;
  private static $_book_pattern;
  private static $_fragment_regexp;

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
