/*!
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * This Program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3, or (at your option)
 * any later version.
 *
 * This Program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * 2020-04-24 TC moOde 6.5.0
 *
 */
var toggleHandler=function(e){var e=e,o=$(e).find("input"),g=function(){o.eq(0).is(":checked")?$(e).removeClass("toggle-off"):$(e).addClass("toggle-off")};g(),o.eq(0).click(function(){$(e).toggleClass("toggle-off")}),o.eq(1).click(function(){$(e).toggleClass("toggle-off")})};$(document).ready(function(){$(".toggle").each(function(e,o){toggleHandler(o)})});
