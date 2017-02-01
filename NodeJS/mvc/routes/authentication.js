var express = require('express');
var restPki = require('../lacuna-restpki'),
    StandardSecurityContexts = restPki.StandardSecurityContexts;
var client = require('../restpki-client');

var router = express.Router();


/*
 * GET /authentication
 *
 * This route initiates an certificate authentication using REST PKI and renders the auth page.
 */
router.get('/', function (req, res, next) {

    // Get an instance of the Authentication class
    var auth = client.getRestPkiClient().getAuthentication();

    // The token acquired can only be used for a single signature attempt. In order to retry the signature it is
    // necessary to get a new token. This can be a problem if the user uses the back button of the browser, since the
    // browser might show a cached page that we rendered previously, with a now stale token. To prevent this from
    // happening,  we set some response headers specifying that the page should not be cached.
    auth.startWithWebPkiAsync(StandardSecurityContexts.pkiBrazil).then(function(token) {

        // The token acquired can only be used for a single signature attempt. In order to retry the signature it is
        // necessary to get a new token. This can be a problem if the user uses the back button of the browser, since
        // the browser might show a cached page that we rendered previously, with a now stale token. To prevent this
        // from happening, we set some response headers specifying that the page should not be cached.
        res.set({
            'Cache-Control': 'private, no-store, max-age=0, no-cache, must-revalidate, post-check=0, pre-check=0',
            'Pragma': 'no-cache'
        });

        // Render the authentication page
        res.render('authentication', {
            token: token
        });

    }).catch(function(error) {
        next(error);
    });
});

/*
 * POST /authentication
 *
 * This route receives the form submission from the view 'authentication'. We'll call REST PKI to complete the
 * authentication.
 */
router.post('/', function (req, res, next) {
    
    // Retrieve the token from the URL
    var token = req.body.token;

    // Get an instance of the Authentication class
    var auth = client.getRestPkiClient().getAuthentication();

    // Call the completeWithWebPki() method with the token, which finalizes the authentication process. The call yields
    // a ValidationResults which denotes whether the authentication was successful or not (we'll use it to render the
    // page accordingly, see below).
    auth.completeWithWebPkiAsync(token).then(function(vr) {

        var userCert = {};
        if (vr.isValid()) {
            // At this point, you have assurance that the certificate is valid according to the SecurityContext
            // specified on the file authentication.php and that the user is indeed the certificate's subject. Now,
            // you'd typically query your database for a user that matches one of the certificate's fields, such as
            // userCert.emailAddress or userCert.pkiBrazil.cpf (the actual field to be used as key depends on your
            // application's business logic) and set the user as authenticated with whatever web security framework
            // your application uses. For demonstration  purposes, we'll just render the user's certificate information.
            userCert = auth.getCertificateInfo();
        }

        // Render the authentication completed page
        res.render('authentication-complete', {
            userCert: userCert,
            validationResults: vr.__toString(),
            isValid: vr.isValid()
        });

    }).catch(function(error) {
        next(error);
    });
});

module.exports = router;
