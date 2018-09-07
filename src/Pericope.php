<?php
namespace PHPericope;

require_once 'pericope/data.php';
require_once 'pericope/Verse.php';
require_once 'pericope/Range.php';

class Pericope {

  public static $max_letter = 'd';

  public static function get_max_verse($book, $chapter) {
    $id = ($book * 1000000) + ($chapter * 1000);
    return CHAPTER_VERSE_COUNTS[$id];
  }

  public static function get_max_chapter($book) {
    return BOOK_CHAPTER_COUNTS[$book];
  }

  public static function letter_regexp() {
    return '/[' . self::letters().implode() . ']$/';
  }

  private static function letters() {
    return range('a', self::max_letter);
  }

}

?>
