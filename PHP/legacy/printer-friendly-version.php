<?php

/*
 * This file generates a printer-friendly version from a signature file using Rest PKI.
 */

// The file RestPkiLegacy.php contains the helper classes to call the REST PKI API for PHP 5.3+. Notice: if you're using
// PHP version 5.5 or greater, please use one of the other samples, which make better use of the extended capabilities
// of the newer versions of PHP - https://github.com/LacunaSoftware/RestPkiSamples/tree/master/PHP
require_once 'RestPkiLegacy.php';

// The file util.php contains the function getRestPkiClient(), which gives us an instance of the RestPkiClient class
// initialized with the API access token
require_once 'util.php';

// The file storage-mock.php contains the mock functions used to store and to retrieve signature codes.
require_once  'storage-mock.php';

use Lacuna\PadesSignatureExplorer;
use Lacuna\StandardSignaturePolicies;
use Lacuna\StandardSecurityContexts;
use Lacuna\PdfMark;
use Lacuna\PdfMarkPageOptions;
use Lacuna\PdfMarkImage;
use Lacuna\PdfMarkImageElement;
use Lacuna\PdfTextSection;
use Lacuna\PdfMarkTextElement;
use Lacuna\PdfMarkQRCodeElement;
use Lacuna\PdfMarker;
use Lacuna\Color;

// #####################################################################################################################
// Configuration of the Printer-Friendly version
// #####################################################################################################################

// Name of your website, with preceding article (article in lowercase).
$verificationSiteNameWithArticle = 'a Minha Central de Verificação';

// Publicly accessible URL of your website. Preferable HTTPS.
$verificationSite = 'http://localhost:8000';

// Format of the verification link, without the verification code, that is added on generatePrinterFriendlyVersion()
// method.
$verificationLinkFormat = 'http://localhost:8000/check.php?c=';

// "Normal" font size. Sizes of header fonts are defined based on this size.
$normalFontSize = 12;

// You may also change texts, positions and more by editing directly the method generatePrinterFriendlyVersion() below.
// #####################################################################################################################

// Get file ID from query string
$fileId = isset($_GET['file']) ? $_GET['file'] : null;
if (empty($fileId)) {
    throw new \Exception("No file was uploaded");
}

// Locate document path
$filePath = 'app-data/' . $fileId;

// Check if doc already has a verification code registered on storage
$verificationCode = getVerificationCode($fileId);
if (!isset($verificationCode)) {
    // If not, generate a code and register it
    $verificationCode = generateVerificationCode();
    setVerificationCode($fileId, $verificationCode);
}

// Generate the printer-friendly version
$pfvContent = generatePrinterFriendlyVersion($filePath, $verificationCode);

// Redirect to the generated file
$pdfPath = 'app-data/' . formatVerificationCode($verificationCode) . '.pdf';
createAppData();
file_put_contents($pdfPath, $pfvContent);
header("Location: {$pdfPath}");
exit;

// This function contains the logic to generate a printer-friendly version of a signature file using the Rest PKI.
function generatePrinterFriendlyVersion($pdfPath, $verificationCode)
{
    // Use global variables defined above
    global $verificationSiteNameWithArticle;
    global $verificationSite;
    global $verificationLinkFormat;
    global $normalFontSize;

    // Use PHP's global variable PHP_EOL that returns a OS independent break-line
    $breakline = PHP_EOL;

    $client = getRestPkiClient();

    // The verification code is generated without hyphens to save storage space and avoid copy-and-paste problems. On
    // the PDF generation, we use the "formatted" version, with hyphens (which will later be discarded on the
    // verification page).
    $formattedVerificationCode = formatVerificationCode($verificationCode);

    // Build the verification link from the constant $verificationLinkFormat (see above) and the formatted verification
    // code.
    $verificationLink = $verificationLinkFormat . $formattedVerificationCode;

    // 1. Inspect signatures on the uploaded PDF
    $signatureExplorer = new PadesSignatureExplorer($client);
    $signatureExplorer->setValidate(true);
    $signatureExplorer->setDefaultSignaturePolicyId(StandardSignaturePolicies::PADES_BASIC);
    $signatureExplorer->setSecurityContextId(StandardSecurityContexts::PKI_BRAZIL);
    $signatureExplorer->setSignatureFile($pdfPath);
    $signature = $signatureExplorer->open();

    // 2. Create PDF wiht verification information from uploaded PDF

    $pdfMarker = new PdfMarker($client);
    $pdfMarker->setFileFromPath($pdfPath);

    // Build string with joined names of signers (see method _getDisplayName below)
    $certDisplayNames = array();
    foreach ($signature->signers as $signer) {
        array_push($certDisplayNames, _getDisplayName($signer->certificate));
    }
    $signerNames = joinStringsPt($certDisplayNames);
    $allPagesMessage = 'Este documento foi assinado digitalmente por ' . $signerNames
        . '.' . $breakline . 'Para verificar a validate das assinaturass acesse ' . $verificationSiteNameWithArticle
        . ' em ' . $verificationSite . ' e informe o código ' . $formattedVerificationCode;

    // ICP-Brasil logo on bottom-right corner of every page (except on the page which will be created at the end of the
    // document)
    $pdfMark = new PdfMark();
    $pdfMark->pageOption = PdfMarkPageOptions::ALL_PAGES;
    $pdfMark->container = array(
        'width' => 1,
        'right' => 1,
        'height' => 1,
        'bottom' => 1
    );
    $element = new PdfMarkImageElement();
    $element->opacity = 75;
    $element->image = new PdfMarkImage(getIcpBrasilLogoContent(), 'image/png');
    array_push($pdfMark->elements, $element);
    array_push($pdfMarker->marks, $pdfMark);

    // Summary on bottom margin of every page (except on the page which will be created at the end of the document)
    $pdfMark = new PdfMark();
    $pdfMark->pageOption = PdfMarkPageOptions::ALL_PAGES;
    $pdfMark->container = array(
        'height' => 2,
        'bottom' => 0,
        'left' => 1.5,
        'right' => 3.5
    );
    $element = new PdfMarkTextElement();
    $element->opacity = 75;
    array_push($element->textSections, new PdfTextSection($allPagesMessage));
    array_push($pdfMark->elements, $element);
    array_push($pdfMarker->marks, $pdfMark);

    // Summary on right margin of every page (except on the page which wil be created at the end of the document),
    // rotated 90 degrees counter-clockwise (text goes up)
    $pdfMark = new PdfMark();
    $pdfMark->pageOption = PdfMarkPageOptions::ALL_PAGES;
    $pdfMark->container = array(
        'width' => 2,
        'right' => 0,
        'top' => 1.5,
        'bottom' => 3.5
    );
    $element = new PdfMarkTextElement();
    $element->rotation = 90;
    $element->opacity = 75;
    array_push($element->textSections, new PdfTextSection($allPagesMessage));
    array_push($pdfMark->elements, $element);
    array_push($pdfMarker->marks, $pdfMark);

    // Create a "manifest" mark on a new page added on the end of the document. We'll add several elements to this mark.
    $manifestMark = new PdfMark();
    $manifestMark->pageOption = PdfMarkPageOptions::NEW_PAGE;
    // This mark's container is the whole page with 1-inch margins
    $manifestMark->container = array(
        'top' => 1.5,
        'bottom' => 1.5,
        'left' => 1.5,
        'right' => 1.5
    );

    // We'll keep track of our "vertical offset" as we add elements to the mark
    $verticalOffset = 0;
    $elementHeight = null;

    $elementHeight = 3;
    // ICP-Brasil logo on the upper-left corner
    $element = new PdfMarkImageElement();
    $element->relativeContainer = array(
        'height' => $elementHeight,
        'top' => $verticalOffset,
        'width' => $elementHeight, // using elementHeight as width because the image has square format
        'left' => 0
    );
    $element->image = new PdfMarkImage(getIcpBrasilLogoContent(), "image/png");
    array_push($manifestMark->elements, $element);

    // QR Code with the verification link on the upper-right corner
    $element = new PdfMarkQRCodeElement();
    $element->relativeContainer = array(
        'height' => $elementHeight,
        'top' => $verticalOffset,
        'width' => $elementHeight, // using elementHeight as width because the image has square format
        'right' => 0
    );
    $element->qrCodeData = $verificationLink;
    array_push($manifestMark->elements, $element);

    // Header "VERIFICAÇÃO DAS ASSINATURAS" centered between ICP-Brasil logo and QR Code
    $element = new PdfMarkTextElement();
    $element->relativeContainer = array(
        'height' => $elementHeight,
        'top' => $verticalOffset + 0.2,
        // Full width
        'left' => 0,
        'right' => 0
    );
    $element->align = 'Center';
    $textSection = new PdfTextSection();
    $textSection->fontSize = $normalFontSize * 1.6;
    $textSection->text = 'VERIFICAÇÃO DAS' . $breakline . 'ASSINATURAS';
    array_push($element->textSections, $textSection);
    array_push($manifestMark->elements, $element);
    $verticalOffset += $elementHeight;

    // Vertical padding
    $verticalOffset += 1.7;

    // Header with verification code
    $elementHeight = 2;
    $element = new PdfMarkTextElement();
    $element->relativeContainer = array(
        'height' => $elementHeight,
        'top' => $verticalOffset,
        // Full width
        'left' => 0,
        'right' => 0
    );
    $element->align = 'Center';
    $textSection = new PdfTextSection();
    $textSection->fontSize = $normalFontSize * 1.2;
    $textSection->text = 'Código para verificação: ' . $formattedVerificationCode;
    array_push($element->textSections, $textSection);
    array_push($manifestMark->elements, $element);
    $verticalOffset += $elementHeight;

    // Paragraph saying "this document was signed by the following signers etc" and mentioning the time zone of the
    // date/times below
    $elementHeight = 2.5;
    $element = new PdfMarkTextElement();
    $element->relativeContainer = array(
        'height' => $elementHeight,
        'top' => $verticalOffset,
        // Full width
        'left' => 0,
        'right' => 0
    );
    $textSection = new PdfTextSection();
    $textSection->fontSize = $normalFontSize;
    $textSection->text = 'Este documento foi assinado digitalmente pelos seguintes signatários nas datas indicadas';
    array_push($element->textSections, $textSection);
    array_push($manifestMark->elements, $element);
    $verticalOffset += $elementHeight;

    // Iterate signers
    foreach ($signature->signers as $signer) {

        $elementHeight = 1.5;

        // Green "check" or red "X" icon depending on result of validation for this signer
        $element = new PdfMarkImageElement();
        $element->relativeContainer = array(
            'height' => 0.5,
            'top' => $verticalOffset + 0.2,
            'width' => 0.5,
            'left' => 0
        );
        $element->image = new PdfMarkImage(getValidationResultIcon($signer->validationResults->isValid()), 'image/png');
        array_push($manifestMark->elements, $element);
        // Description of signer (see method _getSignerDescription() below
        $element = new PdfMarkTextElement();
        $element->relativeContainer = array(
            'height' => $elementHeight,
            'top' => $verticalOffset,
            'left' => 0.8,
            'right' => 0
        );
        $textSection = new PdfTextSection();
        $textSection->fontSize = $normalFontSize;
        $textSection->text = _getSignerDescription($signer);
        array_push($element->textSections, $textSection);
        array_push($manifestMark->elements, $element);

        $verticalOffset += $elementHeight;
    }

    // Some vertical padding from last signer
    $verticalOffset += 1;

    // Paragraph with link to verification site and citing both the verification code above and the verification link
    // below
    $elementHeight = 2.5;
    $element = new PdfMarkTextElement();
    $element->relativeContainer = array(
        'height' => $elementHeight,
        'top' => $verticalOffset,
        // Full width
        'left' => 0,
        'right' => 0
    );
    $textSection = new PdfTextSection();
    $textSection->fontSize = $normalFontSize;
    $textSection->text = 'Para verificar a validade das assinaturas, acesse ' . $verificationSiteNameWithArticle
        . ' em ';
    array_push($element->textSections, $textSection);
    $textSection = new PdfTextSection();
    $textSection->fontSize = $normalFontSize;
    $textSection->color = new Color("#0000FF", 100);
    $textSection->text = $verificationSite;
    array_push($element->textSections, $textSection);
    $textSection = new PdfTextSection();
    $textSection->fontSize = $normalFontSize;
    $textSection->text = ' e informe o código acima ou acesse o link abaixo:';
    array_push($element->textSections, $textSection);
    array_push($manifestMark->elements, $element);
    $verticalOffset += $elementHeight;

    // Verification link
    $elementHeight = 1.5;
    $element = new PdfMarkTextElement();
    $element->relativeContainer = array(
        'height' => $elementHeight,
        'top' => $verticalOffset,
        // Full width
        'left' => 0,
        'right' => 0
    );
    $element->align = 'Center';
    $textSection = new PdfTextSection();
    $textSection->fontSize = $normalFontSize;
    $textSection->color = new Color("#0000FF", 100);
    $textSection->text = $verificationLink;
    array_push($element->textSections, $textSection);
    array_push($manifestMark->elements, $element);

    // Apply marks
    array_push($pdfMarker->marks, $manifestMark);
    $result = $pdfMarker->apply();

    // Return result content raw (binary)
    return base64_decode($result->content);
}

function _getDisplayName($cert)
{
    if (isset($cert->pkiBrazil->responsavel)) {
        return $cert->pkiBrazil->responsavel;
    }
    return $cert->pkiBrazil->commonName;
}

function _getDescription($cert)
{
    $text = '';
    $text .= _getDisplayName($cert);
    if (isset($cert->pkiBrazil->cpf)) {
        $text .= ' (CPF ' . $cert->pkiBrazil->cpfFormatted . ')';
    }
    if (isset($cert->pkiBrazil->cnpj)) {
        $text .= ', empresa ' . $cert->pkiBrazil->companyName . ' (CNPJ ' . $cert->pkiBrazil->cnpjFormatted . ')';
    }
    return $text;
}

function _getSignerDescription($signer)
{
    $text = '';
    $text .= _getDescription($signer->certificate);
    if (isset($signer->signingTime)) {
        $text .= ' em ' . $signer->signingTime;
    }
    return $text;
}