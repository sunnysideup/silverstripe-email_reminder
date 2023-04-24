<!doctype html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>$Subject</title>
<style>
/* -------------------------------------
GLOBAL RESETS
------------------------------------- */

/*All the styling goes here*/

img {
    border: none;
    -ms-interpolation-mode: bicubic;
    max-width: 100%;
    display: block;
    margin: 10px 0;
}

body {
    background-color: #f6f6f6;
    font-family: sans-serif;
    -webkit-font-smoothing: antialiased;
    font-size: 16px;
    line-height: 1.4;
    margin: 0;
    padding: 0;
    -ms-text-size-adjust: 100%;
    -webkit-text-size-adjust: 100%;
}


/* This should also be a block element, so that it will fill 100% of the .container */
.content {
    box-sizing: border-box;
    display: block;
    margin: 0 auto;
    max-width: 580px;
    padding: 10px;
    background: #ffffff;
    border-radius: 3px;
}


/* -------------------------------------
TYPOGRAPHY
------------------------------------- */
h1,
h2,
h3,
h4,
p,
li {
    color: #000000;
    font-family: sans-serif;
    font-weight: 400;
    line-height: 1.4;
}


a {
    color: blue;
    text-decoration: underline;
}


    </style>
</head>
<body>
    <div class="content">
        <header>
            <p>
                <%-- greeting --%>
            </p>
        </header>
        <main>
            $Content.RAW
        </main>
        <footer>
            <p>
                <%-- Footer details --%>
            </p>
        </footer>
    </div>
</body>
</html>
