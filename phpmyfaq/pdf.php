<?php
/**
* $Id: pdf.php,v 1.6 2004-11-21 12:27:21 thorstenr Exp $
*
* @author       Thorsten Rinne <thorsten@phpmyfaq.de>
* @author       Peter Beauvain <pbeauvain@web.de>
* @author       Olivier Plathey <olivier@fpdf.org>
* @author       Krzysztof Kruszynski <thywolf@wolf.homelinux.net>
* @since        2003-02-12
* @copyright    (c) 2001-2004 phpMyFAQ Team
*
* The contents of this file are subject to the Mozilla Public License
* Version 1.1 (the "License"); you may not use this file except in
* compliance with the License. You may obtain a copy of the License at
* http://www.mozilla.org/MPL/
*
* Software distributed under the License is distributed on an "AS IS"
* basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See the
* License for the specific language governing rights and limitations
* under the License.
*/

define("FPDF_FONTPATH", "./font/");
require_once ("inc/data.php");
require_once ("inc/db.php");
require_once ("inc/functions.php");
require_once ("inc/config.php");
require_once ("inc/category.php");

require_once ("inc/fpdf.php");

define("SQLPREFIX", $DB["prefix"]);
$db = new db($DB["type"]);
$db->connect($DB["server"], $DB["user"], $DB["password"], $DB["db"]);
$tree = new Category;

if (isset($_GET["lang"]) && $_GET["lang"] != "" && strlen($_GET["lang"]) <= 2 && !preg_match("=/=", $_GET["lang"])) {
    if (@is_file("lang/language_".$_REQUEST["lang"].".php")) {
        require_once("lang/language_".$_REQUEST["lang"].".php");
        $LANGCODE = $_REQUEST["lang"];
    } else {
        unset($LANGCODE);
    }
}

if (!isset($LANGCODE) && isset($_COOKIE["lang"]) && $_COOKIE["lang"] != "" && strlen($_COOKIE["lang"]) <= 2 && !preg_match("=/=", $_COOKIE["lang"])) {
    if (@is_file("lang/language_".$_COOKIE["lang"].".php")) {
        require_once("lang/language_".$_COOKIE["lang"].".php");
        $LANGCODE = $_COOKIE["lang"];
    } else {
        unset($LANGCODE);
    }
}

if (!isset($LANGCODE) && isset($PMF_CONF["detection"]) && isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])) {
    if (@is_file("lang/language_".substr($_SERVER["HTTP_ACCEPT_LANGUAGE"], 0, 2).".php")) {
        require_once("lang/language_".substr($_SERVER["HTTP_ACCEPT_LANGUAGE"], 0, 2).".php");
        $LANGCODE = substr($_SERVER["HTTP_ACCEPT_LANGUAGE"], 0, 2);
        @setcookie("lang", $LANGCODE, time()+3600);
    } else {
        unset($LANGCODE);
    }
} elseif (!isset($PMF_CONF["detection"])) {
    if (@require_once("lang/".$PMF_CONF["language"])) {
        $LANGCODE = $PMF_LANG["metaLanguage"];
        @setcookie("lang", $LANGCODE, time()+3600);
    } else {
        unset($LANGCODE);
    }
}

if (isset($LANGCODE)) {
    require_once("lang/language_".$LANGCODE.".php");
} else {
    $LANGCODE = "en";
    require_once ("lang/language_en.php");
}

class PDF extends FPDF
{
    var $B;
    var $I;
    var $U;
    var $SRC;
    var $HREF;
    var $PRE;
    var $CENTER;
    var $tableborder;
    var $tdbegin;
    var $tdwidth;
    var $tdheight;
    var $tdalign;
    var $tdbgcolor;
    
    // PDF Konstruktor
    function PDF ($rubrik = "", $thema = "", $categories = "", $orientation = "P", $unit = "mm", $format = "A4")
    {
        $this->rubrik = $rubrik;
        $this->thema = $thema;
        $this->categories = $categories;
        $this->FPDF($orientation, $unit, $format);
        $this->B = 0;
        $this->I = 0;
        $this->U = 0;
        $this->PRE = 0;
        $this->CENTER = 0;
        $this->SRC = "";
        $this->HREF = "";
        $this->tableborder = 0;
        $this->tdbegin = FALSE;
        $this->tdwidth = 0;
        $this->tdheight = 0;
        $this->tdalign = "L";
        $this->tdbgcolor = FALSE;
    }
    
	// PDF-Header
	function Header() {
		$title = stripslashes($this->categories[$this->rubrik]["name"]).": ".stripslashes($this->thema);
		$this->SetFont("Arial", "I", 18);
		$this->MultiCell(0, 9, $title, 1, 1, "C", 1);
		$this->Ln(8);
		}
	
	// PDF-Footer
	function Footer() {
        global $cat, $PMF_CONF, $PMF_LANG;
    	$this->SetY(-25);
    	$this->SetFont("Arial", "I", 10);
    	$this->Cell(0, 10, $PMF_LANG["ad_gen_page"]." ".$this->PageNo()."/{nb}",0,0,"C");
		$this->SetY(-20);
    	$this->SetFont("Arial", "B", 8);
		$this->Cell(0, 10, "(c) ".date("Y")." ".$PMF_CONF["metaPublisher"]." <".$PMF_CONF["adminmail"].">",0,1,"C");
		$this->SetY(-15);
    	$this->SetFont("Arial", "", 8);
		$this->Cell(0, 10, "URL: http://".$_SERVER["HTTP_HOST"].str_replace("pdf.php", "index.php?action=artikel&cat=".$this->categories[$this->rubrik]["id"]."&id=".$_REQUEST["id"]."&artlang=".$_REQUEST["lang"], $_SERVER["PHP_SELF"]),0,1,"C");
		}
    
    // HTML-Parser
	function WriteHTML($html)
    {
        $html = str_replace("\n", "<br />", $html);
        
        $a = preg_split("/<(.*)>/U", $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach($a as $i => $e) {
            if ($i % 2 == 0) {
                if ($this->HREF) {
                    $this->PutLink($this->HREF,$e);
                    }
                elseif ($this->SRC) {
                    $this->AddImage($this->SRC);
                    $this->SRC = "";
                    }
                elseif ($this->CENTER) {
                    $this->MultiCell(0, 1, $e, 0, "L");
                    }
                elseif($this->tdbegin) {
                    if(trim($e) != '' && $e != "&nbsp;") {
                        $this->Cell($this->tdwidth, $this->tdheight, $e, $this->tableborder, '', $this->tdalign, $this->tdbgcolor);
                        }
                     elseif($e=="&nbsp;") {
                        $this->Cell($this->tdwidth, $this->tdheight, '', $this->tableborder, '' ,$this->tdalign, $this->tdbgcolor);
                        }
                    }
                else {
                    $this->Write(5,$e);
                    }
                }
            else {
                if ($e{0} == "/") {
                    $this->CloseTag(strtoupper(substr($e,1)));
                    }
                else {
                    $a2 = explode(" ",$e);
                    $tag = strtoupper(array_shift($a2));
                    $attr = array();
                    foreach ($a2 as $v) {
                        if (ereg('^([^=]*)=["\']?([^"\']*)["\']?$',$v,$a3)) {
                            $attr[strtoupper($a3[1])]=$a3[2];
                            }
                        }
                    $this->OpenTag($tag,$attr);
                    }
                }
            }
    }
    
    /**
    * Locate the supported tags and set, what to do next
    *
    * @param    string
    * @param    array
    * @return   void
    * @access   private
    */
	function OpenTag($tag, $attr)
    {
        switch ($tag) {
            case "STRONG":  
            case "B":       
                            $this->SetStyle('B', TRUE);
                            break;
            case "EM":      
            case "I":       
                            $this->SetStyle('I', TRUE);
                            break;
            case "U":       
                            $this->SetStyle('U', TRUE);
            case "PRE":     
                            $this->SetFont("Courier", "", 10);
			                $this->SetTextColor(0,0,255);
                            break;
            case "A":       
                            $this->HREF = $attr["HREF"];
                            break;
            case "IMG":     
                            $this->SRC = $attr["SRC"];
                            break;
    	    case "DIV":     
                            if ($attr["ALIGN"] != "justify") {
                                $this->CENTER = $attr["ALIGN"];
                            }
                            break;
            case "UL":      
                            $this->SetLeftMargin($this->lMargin + 10);
                            break;
            case "LI":      
                            $this->Ln();
                            $this->SetX($this->GetX() - 10);
                            $this->Cell(10, 5, chr(149), 0, 0, 'C');
                            break;
            case "P":       
            case "BR":      
                            $this->Ln(5);
    			            break;
            case "TABLE":   
                            if ($attr['BORDER'] != "") {
                                $this->tableborder = $attr['BORDER'];
                            } else {
                                $this->tableborder = 0;
                            }
                            break;
            case "TR":      
                            break;
            case "TD":      
                            
                            if ($attr['WIDTH'] != "") {
                                $this->tdwidth = ($attr['WIDTH'] / 4);
                            } else {
                                $this->tdwidth = 40;
                            }
                            if ($attr['HEIGHT'] != "") {
                                $this->tdheight = ($attr['HEIGHT'] / 6);
                            } else {
                                $this->tdheight = 6;
                            }
                            if ($attr['ALIGN'] != "") {
                                $align = $attr['ALIGN'];
                                if ($align == "LEFT") {
                                    $this->tdalign = "L";
                                }
                                if ($align == "CENTER") {
                                    $this->tdalign = "C";
                                }
                                if ($align == "RIGHT") {
                                    $this->tdalign = "R";
                                }
                            } else {
                                $this->tdalign = "L";
                            }
                            if ($attr['BGCOLOR'] != "") {
                                $color = hex2dec($attr['BGCOLOR']);
                                $this->SetFillColor($color['R'], $color['G'], $color['B']);
                                $this->tdbgcolor = TRUE;
                            }
                            $this->tdbegin = TRUE;
                            break;
            case "HR":      
                            $this->PutLine();
                            break;
            default:        
                            break;
        }
    }
	
	function CloseTag($tag)
    {
		if ($tag == "B" or $tag == "I" or $tag == "U" or $tag == "STRONG" or $tag == "EM") {
            if ($tag == "STRONG") {
                $tag = "B";
                }
            if ($tag == "EM") {
                $tag = "I";
                }
			$this->SetStyle($tag, FALSE);
			}
		if ($tag == "PRE") {
			$this->SetFont("Arial", "", 12);
			$this->SetTextColor(0,0,0);
			}
		if ($tag == "A") {
			$this->HREF = "";
			}
		if ($tag == "DIV") {
			$this->CENTER = "";
			}
         if ($tag == "UL") {
            $this->SetLeftMargin($this->lMargin - 10);
            $this->Ln();
            }
        if ($tag == "TD") {
            $this->tdbegin = FALSE;
            $this->tdwidth = 0;
            $this->tdheight = 0;
            $this->tdalign = "L";
            $this->tdbgcolor = FALSE;
            }
        if ($tag == "TR") {
            $this->Ln();
            }
        if ($tag == "TABLE") {
            $this->tableborder = 0;
            }
        if ($tag == "P") {
            $this->Ln(5);
        }
    }
	
	function SetStyle($tag, $enable)
    {
		$this->$tag += ($enable ? 1 : -1);
		$style = "";
		foreach (array("B", "I", "U") as $s) {
			if ($this->$s > 0) {
				$style .= $s;
				}
			}
		$this->SetFont("", $style);
    }
	
	function PutLink($URL, $txt)
    {
		$this->SetTextColor(0, 0, 255);
		$this->SetStyle("U", TRUE);
		$this->Write(5, $txt, $URL);
		$this->SetStyle("U", FALSE);
		$this->SetTextColor(0);
    }
	
	function AddImage($image)
    {
        $image = "..".$image; // what a stupid hack :-)
        $info = GetImageSize("$image");
        if ($info[0] > 555 ) {
            $w = $info[0] / 144 * 25.4;
            $h = $info[1] / 144 * 25.4;
            }
        else {
            $w = $info[0] / 72 * 25.4;
            $h = $info[1] / 72 * 25.4;
            }
        $hw_ratio = $h / $w;
        $this->Write(5,' ');
        if ($info[0] > $this->wPt) {
            $info[0] = $this->wPt - $this->lMargin - $this->rMargin;
            if ($w > $this->w) {
                $w = $this->w - $this->lMargin - $this->rMargin;
                $h = $w*$hw_ratio;
                }
            }
        
        $x = $this->GetX();
        
        if ($this->GetY() + $h > $this->h) {
            $this->AddPage();
            }
        $y = $this->GetY();
        $this->Image($image, $x, $y, $w, $h);
        $this->Write(5,' ');
        
        $y = $this->GetY();
        $this->Image($image, $x, $y, $w, $h);
        if ($y + $h > $this->hPt) {
            $this->AddPage();
            }
        else {
            if ($info[1] > 20 ) {
                $this->SetY($y+$h);
                }
            $this->SetX($x+$w);
            }
    }
}

if (isset($_GET["id"]) && checkIntVar($_GET["id"]) == TRUE) {
	$id = $_GET["id"];
	}
if (isset($_GET["lang"]) && strlen($_GET["lang"]) <= 2 && !preg_match("=/=", $_GET["lang"])) {
    $lang = $_GET["lang"];
    }

$result = $db->query("SELECT id, lang, rubrik, thema, content, datum, author FROM ".SQLPREFIX."faqdata WHERE id = '".$id."' AND lang = '".$lang."' AND active = 'yes'");
if ($db->num_rows($result) > 0) {
	while ($row = $db->fetch_object($result)) {
		$lang = $row->lang;
		$rubrik = $row->rubrik;
		$thema = $row->thema;
		$content = $row->content;
		$date = $row->datum;
		$author = $row->author;
		}
	}
else {
	print "Error!";
	}

$pdf = new PDF($rubrik, $thema, $tree->categoryName, $orientation = "P", $unit = "mm", $format = "A4");
$pdf->Open();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont("Arial", "", 12);
$pdf->SetDisplayMode("real");
// FIXME: Header
$pdf->WriteHTML(str_replace("../", "", stripslashes($content)));
$pdf->Ln();
$pdf->Ln();
$pdf->Write(5,unhtmlentities($PMF_LANG["msgAuthor"]).$author);
$pdf->Ln();
$pdf->Write(5,unhtmlentities($PMF_LANG["msgLastUpdateArticle"]).makeDate($date));
// FIXME: Footer

$pdfFile = "pdf/".$id.".pdf";
$pdf->Output($pdfFile);
$pdf->close($pdfFile);

$file = basename($pdfFile);
$size = filesize($pdfFile);
session_cache_limiter('private'); 
header("Pragma: public");
header("Expires: 0"); // set expiration time
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

if (preg_match("/MSIE/i", $_SERVER["HTTP_USER_AGENT"])) {
    header("Content-type: application/pdf");
    header("Content-Transfer-Encoding: binary");
    header("Content-Length: ".filesize($pdfFile));
    header("Content-Disposition: Attachment; filename=".$id.".pdf" );  
    readfile($pdfFile);
    }
else {
    header("Location: ".$pdfFile."");
    header("Content-Type: application/pdf");
    header("Content-Length: ".filesize($pdfFile));
    readfile($pdfFile);
    }