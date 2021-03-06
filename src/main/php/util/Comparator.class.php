<?php namespace util;

/**
 * Comparator interface 
 *
 * @see      xp://util.Hashmap#usort
 * @see      php://usort
 */
interface Comparator {

  /**
   * Compares its two arguments for order. Returns a negative integer, 
   * zero, or a positive integer as the first argument is less than, 
   * equal to, or greater than the second.
   *
   * @param   var a
   * @param   var b
   * @return  int
   */
  public function compare($a, $b);
}
