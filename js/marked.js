/**
 * marked v5.0.5 - a markdown parser
 * Copyright (c) 2011-2023, Christopher Jeffrey. (MIT Licensed)
 * https://github.com/markedjs/marked
 */
!function(e, u) {
    "object" == typeof exports && "undefined" != typeof module ? u(exports) : "function" == typeof define && define.amd ? define(["exports"], u) : u((e = "undefined" != typeof globalThis ? globalThis : e || self).marked = {})
}(this, function(r) {
    "use strict";
    function i(e, u) {
        for (var t = 0; t < u.length; t++) {
            var n = u[t];
            n.enumerable = n.enumerable || !1,
            n.configurable = !0,
            "value"in n && (n.writable = !0),
            Object.defineProperty(e, function(e) {
                e = function(e, u) {
                    if ("object" != typeof e || null === e)
                        return e;
                    var t = e[Symbol.toPrimitive];
                    if (void 0 === t)
                        return ("string" === u ? String : Number)(e);
                    t = t.call(e, u || "default");
                    if ("object" != typeof t)
                        return t;
                    throw new TypeError("@@toPrimitive must return a primitive value.")
                }(e, "string");
                return "symbol" == typeof e ? e : String(e)
            }(n.key), n)
        }
    }
    function A() {
        return (A = Object.assign ? Object.assign.bind() : function(e) {
            for (var u = 1; u < arguments.length; u++) {
                var t, n = arguments[u];
                for (t in n)
                    Object.prototype.hasOwnProperty.call(n, t) && (e[t] = n[t])
            }
            return e
        }
        ).apply(this, arguments)
    }
    function s(e, u) {
        (null == u || u > e.length) && (u = e.length);
        for (var t = 0, n = new Array(u); t < u; t++)
            n[t] = e[t];
        return n
    }
    function D(e, u) {
        var t, n = "undefined" != typeof Symbol && e[Symbol.iterator] || e["@@iterator"];
        if (n)
            return (n = n.call(e)).next.bind(n);
        if (Array.isArray(e) || (n = function(e, u) {
            var t;
            if (e)
                return "string" == typeof e ? s(e, u) : "Map" === (t = "Object" === (t = Object.prototype.toString.call(e).slice(8, -1)) && e.constructor ? e.constructor.name : t) || "Set" === t ? Array.from(e) : "Arguments" === t || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(t) ? s(e, u) : void 0
        }(e)) || u && e && "number" == typeof e.length)
            return n && (e = n),
            t = 0,
            function() {
                return t >= e.length ? {
                    done: !0
                } : {
                    done: !1,
                    value: e[t++]
                }
            }
            ;
        throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")
    }
    function e() {
        return {
            async: !1,
            baseUrl: null,
            breaks: !1,
            extensions: null,
            gfm: !0,
            headerIds: !0,
            headerPrefix: "",
            highlight: null,
            hooks: null,
            langPrefix: "language-",
            mangle: !0,
            pedantic: !1,
            renderer: null,
            sanitize: !1,
            sanitizer: null,
            silent: !1,
            smartypants: !1,
            tokenizer: null,
            walkTokens: null,
            xhtml: !1
        }
    }
    r.defaults = e();
    function t(e) {
        return u[e]
    }
    var n = /[&<>"']/
      , a = new RegExp(n.source,"g")
      , l = /[<>"']|&(?!(#\d{1,7}|#[Xx][a-fA-F0-9]{1,6}|\w+);)/
      , o = new RegExp(l.source,"g")
      , u = {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;"
    };
    function d(e, u) {
        if (u) {
            if (n.test(e))
                return e.replace(a, t)
        } else if (l.test(e))
            return e.replace(o, t);
        return e
    }
    var c = /&(#(?:\d+)|(?:#x[0-9A-Fa-f]+)|(?:\w+));?/gi;
    function x(e) {
        return e.replace(c, function(e, u) {
            return "colon" === (u = u.toLowerCase()) ? ":" : "#" === u.charAt(0) ? "x" === u.charAt(1) ? String.fromCharCode(parseInt(u.substring(2), 16)) : String.fromCharCode(+u.substring(1)) : ""
        })
    }
    var p = /(^|[^\[])\^/g;
    function h(t, e) {
        t = "string" == typeof t ? t : t.source,
        e = e || "";
        var n = {
            replace: function(e, u) {
                return u = (u = u.source || u).replace(p, "$1"),
                t = t.replace(e, u),
                n
            },
            getRegex: function() {
                return new RegExp(t,e)
            }
        };
        return n
    }
    var F = /[^\w:]/g
      , P = /^$|^[a-z][a-z0-9+.-]*:|^[?#]/i;
    function f(e, u, t) {
        if (e) {
            try {
                n = decodeURIComponent(x(t)).replace(F, "").toLowerCase()
            } catch (e) {
                return null
            }
            if (0 === n.indexOf("javascript:") || 0 === n.indexOf("vbscript:") || 0 === n.indexOf("data:"))
                return null
        }
        var n;
        u && !P.test(t) && (e = t,
        g[" " + (n = u)] || (j.test(n) ? g[" " + n] = n + "/" : g[" " + n] = E(n, "/", !0)),
        u = -1 === (n = g[" " + n]).indexOf(":"),
        t = "//" === e.substring(0, 2) ? u ? e : n.replace(Z, "$1") + e : "/" === e.charAt(0) ? u ? e : n.replace(O, "$1") + e : n + e);
        try {
            t = encodeURI(t).replace(/%25/g, "%")
        } catch (e) {
            return null
        }
        return t
    }
    var g = {}
      , j = /^[^:]+:\/*[^/]*$/
      , Z = /^([^:]+:)[\s\S]*$/
      , O = /^([^:]+:\/*[^/]*)[\s\S]*$/;
    var C = {
        exec: function() {}
    };
    function k(e, u) {
        var t = e.replace(/\|/g, function(e, u, t) {
            for (var n = !1, r = u; 0 <= --r && "\\" === t[r]; )
                n = !n;
            return n ? "|" : " |"
        }).split(/ \|/)
          , n = 0;
        if (t[0].trim() || t.shift(),
        0 < t.length && !t[t.length - 1].trim() && t.pop(),
        t.length > u)
            t.splice(u);
        else
            for (; t.length < u; )
                t.push("");
        for (; n < t.length; n++)
            t[n] = t[n].trim().replace(/\\\|/g, "|");
        return t
    }
    function E(e, u, t) {
        var n = e.length;
        if (0 === n)
            return "";
        for (var r = 0; r < n; ) {
            var i = e.charAt(n - r - 1);
            if ((i !== u || t) && (i === u || !t))
                break;
            r++
        }
        return e.slice(0, n - r)
    }
    function m(e, u, t, n) {
        var r = u.href
          , u = u.title ? d(u.title) : null
          , i = e[1].replace(/\\([\[\]])/g, "$1");
        return "!" !== e[0].charAt(0) ? (n.state.inLink = !0,
        e = {
            type: "link",
            raw: t,
            href: r,
            title: u,
            text: i,
            tokens: n.inlineTokens(i)
        },
        n.state.inLink = !1,
        e) : {
            type: "image",
            raw: t,
            href: r,
            title: u,
            text: d(i)
        }
    }
    var b = function() {
        function e(e) {
            this.options = e || r.defaults
        }
        var u = e.prototype;
        return u.space = function(e) {
            e = this.rules.block.newline.exec(e);
            if (e && 0 < e[0].length)
                return {
                    type: "space",
                    raw: e[0]
                }
        }
        ,
        u.code = function(e) {
            var u, e = this.rules.block.code.exec(e);
            if (e)
                return u = e[0].replace(/^ {1,4}/gm, ""),
                {
                    type: "code",
                    raw: e[0],
                    codeBlockStyle: "indented",
                    text: this.options.pedantic ? u : E(u, "\n")
                }
        }
        ,
        u.fences = function(e) {
            var u, t, n, r, e = this.rules.block.fences.exec(e);
            if (e)
                return u = e[0],
                t = u,
                n = e[3] || "",
                t = null === (t = u.match(/^(\s+)(?:```)/)) ? n : (r = t[1],
                n.split("\n").map(function(e) {
                    var u = e.match(/^\s+/);
                    return null !== u && u[0].length >= r.length ? e.slice(r.length) : e
                }).join("\n")),
                {
                    type: "code",
                    raw: u,
                    lang: e[2] && e[2].trim().replace(this.rules.inline._escapes, "$1"),
                    text: t
                }
        }
        ,
        u.heading = function(e) {
            var u, t, e = this.rules.block.heading.exec(e);
            if (e)
                return u = e[2].trim(),
                /#$/.test(u) && (t = E(u, "#"),
                !this.options.pedantic && t && !/ $/.test(t) || (u = t.trim())),
                {
                    type: "heading",
                    raw: e[0],
                    depth: e[1].length,
                    text: u,
                    tokens: this.lexer.inline(u)
                }
        }
        ,
        u.hr = function(e) {
            e = this.rules.block.hr.exec(e);
            if (e)
                return {
                    type: "hr",
                    raw: e[0]
                }
        }
        ,
        u.blockquote = function(e) {
            var u, t, n, e = this.rules.block.blockquote.exec(e);
            if (e)
                return u = e[0].replace(/^ *>[ \t]?/gm, ""),
                t = this.lexer.state.top,
                this.lexer.state.top = !0,
                n = this.lexer.blockTokens(u),
                this.lexer.state.top = t,
                {
                    type: "blockquote",
                    raw: e[0],
                    tokens: n,
                    text: u
                }
        }
        ,
        u.list = function(e) {
            var u = this.rules.block.list.exec(e);
            if (u) {
                var t, n, r, i, s, a, l, o, D, c, p, h = 1 < (f = u[1].trim()).length, F = {
                    type: "list",
                    raw: "",
                    ordered: h,
                    start: h ? +f.slice(0, -1) : "",
                    loose: !1,
                    items: []
                }, f = h ? "\\d{1,9}\\" + f.slice(-1) : "\\" + f;
                this.options.pedantic && (f = h ? f : "[*+-]");
                for (var g = new RegExp("^( {0,3}" + f + ")((?:[\t ][^\\n]*)?(?:\\n|$))"); e && (p = !1,
                u = g.exec(e)) && !this.rules.block.hr.test(e); ) {
                    if (t = u[0],
                    e = e.substring(t.length),
                    l = u[2].split("\n", 1)[0].replace(/^\t+/, function(e) {
                        return " ".repeat(3 * e.length)
                    }),
                    o = e.split("\n", 1)[0],
                    this.options.pedantic ? (i = 2,
                    c = l.trimLeft()) : (i = u[2].search(/[^ ]/),
                    c = l.slice(i = 4 < i ? 1 : i),
                    i += u[1].length),
                    s = !1,
                    !l && /^ *$/.test(o) && (t += o + "\n",
                    e = e.substring(o.length + 1),
                    p = !0),
                    !p)
                        for (var A = new RegExp("^ {0," + Math.min(3, i - 1) + "}(?:[*+-]|\\d{1,9}[.)])((?:[ \t][^\\n]*)?(?:\\n|$))"), d = new RegExp("^ {0," + Math.min(3, i - 1) + "}((?:- *){3,}|(?:_ *){3,}|(?:\\* *){3,})(?:\\n+|$)"), C = new RegExp("^ {0," + Math.min(3, i - 1) + "}(?:```|~~~)"), k = new RegExp("^ {0," + Math.min(3, i - 1) + "}#"); e && (o = D = e.split("\n", 1)[0],
                        this.options.pedantic && (o = o.replace(/^ {1,4}(?=( {4})*[^ ])/g, "  ")),
                        !C.test(o)) && !k.test(o) && !A.test(o) && !d.test(e); ) {
                            if (o.search(/[^ ]/) >= i || !o.trim())
                                c += "\n" + o.slice(i);
                            else {
                                if (s)
                                    break;
                                if (4 <= l.search(/[^ ]/))
                                    break;
                                if (C.test(l))
                                    break;
                                if (k.test(l))
                                    break;
                                if (d.test(l))
                                    break;
                                c += "\n" + o
                            }
                            s || o.trim() || (s = !0),
                            t += D + "\n",
                            e = e.substring(D.length + 1),
                            l = o.slice(i)
                        }
                    F.loose || (a ? F.loose = !0 : /\n *\n *$/.test(t) && (a = !0)),
                    this.options.gfm && (n = /^\[[ xX]\] /.exec(c)) && (r = "[ ] " !== n[0],
                    c = c.replace(/^\[[ xX]\] +/, "")),
                    F.items.push({
                        type: "list_item",
                        raw: t,
                        task: !!n,
                        checked: r,
                        loose: !1,
                        text: c
                    }),
                    F.raw += t
                }
                F.items[F.items.length - 1].raw = t.trimRight(),
                F.items[F.items.length - 1].text = c.trimRight(),
                F.raw = F.raw.trimRight();
                for (var E, x = F.items.length, m = 0; m < x; m++)
                    this.lexer.state.top = !1,
                    F.items[m].tokens = this.lexer.blockTokens(F.items[m].text, []),
                    F.loose || (E = 0 < (E = F.items[m].tokens.filter(function(e) {
                        return "space" === e.type
                    })).length && E.some(function(e) {
                        return /\n.*\n/.test(e.raw)
                    }),
                    F.loose = E);
                if (F.loose)
                    for (m = 0; m < x; m++)
                        F.items[m].loose = !0;
                return F
            }
        }
        ,
        u.html = function(e) {
            var u, e = this.rules.block.html.exec(e);
            if (e)
                return u = {
                    type: "html",
                    block: !0,
                    raw: e[0],
                    pre: !this.options.sanitizer && ("pre" === e[1] || "script" === e[1] || "style" === e[1]),
                    text: e[0]
                },
                this.options.sanitize && (e = this.options.sanitizer ? this.options.sanitizer(e[0]) : d(e[0]),
                u.type = "paragraph",
                u.text = e,
                u.tokens = this.lexer.inline(e)),
                u
        }
        ,
        u.def = function(e) {
            var u, t, n, e = this.rules.block.def.exec(e);
            if (e)
                return u = e[1].toLowerCase().replace(/\s+/g, " "),
                t = e[2] ? e[2].replace(/^<(.*)>$/, "$1").replace(this.rules.inline._escapes, "$1") : "",
                n = e[3] && e[3].substring(1, e[3].length - 1).replace(this.rules.inline._escapes, "$1"),
                {
                    type: "def",
                    tag: u,
                    raw: e[0],
                    href: t,
                    title: n
                }
        }
        ,
        u.table = function(e) {
            e = this.rules.block.table.exec(e);
            if (e) {
                var u = {
                    type: "table",
                    header: k(e[1]).map(function(e) {
                        return {
                            text: e
                        }
                    }),
                    align: e[2].replace(/^ *|\| *$/g, "").split(/ *\| */),
                    rows: e[3] && e[3].trim() ? e[3].replace(/\n[ \t]*$/, "").split("\n") : []
                };
                if (u.header.length === u.align.length) {
                    u.raw = e[0];
                    for (var t, n, r, i = u.align.length, s = 0; s < i; s++)
                        /^ *-+: *$/.test(u.align[s]) ? u.align[s] = "right" : /^ *:-+: *$/.test(u.align[s]) ? u.align[s] = "center" : /^ *:-+ *$/.test(u.align[s]) ? u.align[s] = "left" : u.align[s] = null;
                    for (i = u.rows.length,
                    s = 0; s < i; s++)
                        u.rows[s] = k(u.rows[s], u.header.length).map(function(e) {
                            return {
                                text: e
                            }
                        });
                    for (i = u.header.length,
                    t = 0; t < i; t++)
                        u.header[t].tokens = this.lexer.inline(u.header[t].text);
                    for (i = u.rows.length,
                    t = 0; t < i; t++)
                        for (r = u.rows[t],
                        n = 0; n < r.length; n++)
                            r[n].tokens = this.lexer.inline(r[n].text);
                    return u
                }
            }
        }
        ,
        u.lheading = function(e) {
            e = this.rules.block.lheading.exec(e);
            if (e)
                return {
                    type: "heading",
                    raw: e[0],
                    depth: "=" === e[2].charAt(0) ? 1 : 2,
                    text: e[1],
                    tokens: this.lexer.inline(e[1])
                }
        }
        ,
        u.paragraph = function(e) {
            var u, e = this.rules.block.paragraph.exec(e);
            if (e)
                return u = "\n" === e[1].charAt(e[1].length - 1) ? e[1].slice(0, -1) : e[1],
                {
                    type: "paragraph",
                    raw: e[0],
                    text: u,
                    tokens: this.lexer.inline(u)
                }
        }
        ,
        u.text = function(e) {
            e = this.rules.block.text.exec(e);
            if (e)
                return {
                    type: "text",
                    raw: e[0],
                    text: e[0],
                    tokens: this.lexer.inline(e[0])
                }
        }
        ,
        u.escape = function(e) {
            e = this.rules.inline.escape.exec(e);
            if (e)
                return {
                    type: "escape",
                    raw: e[0],
                    text: d(e[1])
                }
        }
        ,
        u.tag = function(e) {
            e = this.rules.inline.tag.exec(e);
            if (e)
                return !this.lexer.state.inLink && /^<a /i.test(e[0]) ? this.lexer.state.inLink = !0 : this.lexer.state.inLink && /^<\/a>/i.test(e[0]) && (this.lexer.state.inLink = !1),
                !this.lexer.state.inRawBlock && /^<(pre|code|kbd|script)(\s|>)/i.test(e[0]) ? this.lexer.state.inRawBlock = !0 : this.lexer.state.inRawBlock && /^<\/(pre|code|kbd|script)(\s|>)/i.test(e[0]) && (this.lexer.state.inRawBlock = !1),
                {
                    type: this.options.sanitize ? "text" : "html",
                    raw: e[0],
                    inLink: this.lexer.state.inLink,
                    inRawBlock: this.lexer.state.inRawBlock,
                    block: !1,
                    text: this.options.sanitize ? this.options.sanitizer ? this.options.sanitizer(e[0]) : d(e[0]) : e[0]
                }
        }
        ,
        u.link = function(e) {
            e = this.rules.inline.link.exec(e);
            if (e) {
                var u = e[2].trim();
                if (!this.options.pedantic && /^</.test(u)) {
                    if (!/>$/.test(u))
                        return;
                    var t = E(u.slice(0, -1), "\\");
                    if ((u.length - t.length) % 2 == 0)
                        return
                } else {
                    t = function(e, u) {
                        if (-1 !== e.indexOf(u[1]))
                            for (var t = e.length, n = 0, r = 0; r < t; r++)
                                if ("\\" === e[r])
                                    r++;
                                else if (e[r] === u[0])
                                    n++;
                                else if (e[r] === u[1] && --n < 0)
                                    return r;
                        return -1
                    }(e[2], "()");
                    -1 < t && (r = (0 === e[0].indexOf("!") ? 5 : 4) + e[1].length + t,
                    e[2] = e[2].substring(0, t),
                    e[0] = e[0].substring(0, r).trim(),
                    e[3] = "")
                }
                var n, t = e[2], r = "";
                return this.options.pedantic ? (n = /^([^'"]*[^\s])\s+(['"])(.*)\2/.exec(t)) && (t = n[1],
                r = n[3]) : r = e[3] ? e[3].slice(1, -1) : "",
                t = t.trim(),
                m(e, {
                    href: (t = /^</.test(t) ? this.options.pedantic && !/>$/.test(u) ? t.slice(1) : t.slice(1, -1) : t) && t.replace(this.rules.inline._escapes, "$1"),
                    title: r && r.replace(this.rules.inline._escapes, "$1")
                }, e[0], this.lexer)
            }
        }
        ,
        u.reflink = function(e, u) {
            var t;
            if (t = (t = this.rules.inline.reflink.exec(e)) || this.rules.inline.nolink.exec(e))
                return (e = u[(e = (t[2] || t[1]).replace(/\s+/g, " ")).toLowerCase()]) ? m(t, e, t[0], this.lexer) : {
                    type: "text",
                    raw: u = t[0].charAt(0),
                    text: u
                }
        }
        ,
        u.emStrong = function(e, u, t) {
            void 0 === t && (t = "");
            var n = this.rules.inline.emStrong.lDelim.exec(e);
            if (n && (!n[3] || !t.match(/(?:[0-9A-Za-z\xAA\xB2\xB3\xB5\xB9\xBA\xBC-\xBE\xC0-\xD6\xD8-\xF6\xF8-\u02C1\u02C6-\u02D1\u02E0-\u02E4\u02EC\u02EE\u0370-\u0374\u0376\u0377\u037A-\u037D\u037F\u0386\u0388-\u038A\u038C\u038E-\u03A1\u03A3-\u03F5\u03F7-\u0481\u048A-\u052F\u0531-\u0556\u0559\u0560-\u0588\u05D0-\u05EA\u05EF-\u05F2\u0620-\u064A\u0660-\u0669\u066E\u066F\u0671-\u06D3\u06D5\u06E5\u06E6\u06EE-\u06FC\u06FF\u0710\u0712-\u072F\u074D-\u07A5\u07B1\u07C0-\u07EA\u07F4\u07F5\u07FA\u0800-\u0815\u081A\u0824\u0828\u0840-\u0858\u0860-\u086A\u0870-\u0887\u0889-\u088E\u08A0-\u08C9\u0904-\u0939\u093D\u0950\u0958-\u0961\u0966-\u096F\u0971-\u0980\u0985-\u098C\u098F\u0990\u0993-\u09A8\u09AA-\u09B0\u09B2\u09B6-\u09B9\u09BD\u09CE\u09DC\u09DD\u09DF-\u09E1\u09E6-\u09F1\u09F4-\u09F9\u09FC\u0A05-\u0A0A\u0A0F\u0A10\u0A13-\u0A28\u0A2A-\u0A30\u0A32\u0A33\u0A35\u0A36\u0A38\u0A39\u0A59-\u0A5C\u0A5E\u0A66-\u0A6F\u0A72-\u0A74\u0A85-\u0A8D\u0A8F-\u0A91\u0A93-\u0AA8\u0AAA-\u0AB0\u0AB2\u0AB3\u0AB5-\u0AB9\u0ABD\u0AD0\u0AE0\u0AE1\u0AE6-\u0AEF\u0AF9\u0B05-\u0B0C\u0B0F\u0B10\u0B13-\u0B28\u0B2A-\u0B30\u0B32\u0B33\u0B35-\u0B39\u0B3D\u0B5C\u0B5D\u0B5F-\u0B61\u0B66-\u0B6F\u0B71-\u0B77\u0B83\u0B85-\u0B8A\u0B8E-\u0B90\u0B92-\u0B95\u0B99\u0B9A\u0B9C\u0B9E\u0B9F\u0BA3\u0BA4\u0BA8-\u0BAA\u0BAE-\u0BB9\u0BD0\u0BE6-\u0BF2\u0C05-\u0C0C\u0C0E-\u0C10\u0C12-\u0C28\u0C2A-\u0C39\u0C3D\u0C58-\u0C5A\u0C5D\u0C60\u0C61\u0C66-\u0C6F\u0C78-\u0C7E\u0C80\u0C85-\u0C8C\u0C8E-\u0C90\u0C92-\u0CA8\u0CAA-\u0CB3\u0CB5-\u0CB9\u0CBD\u0CDD\u0CDE\u0CE0\u0CE1\u0CE6-\u0CEF\u0CF1\u0CF2\u0D04-\u0D0C\u0D0E-\u0D10\u0D12-\u0D3A\u0D3D\u0D4E\u0D54-\u0D56\u0D58-\u0D61\u0D66-\u0D78\u0D7A-\u0D7F\u0D85-\u0D96\u0D9A-\u0DB1\u0DB3-\u0DBB\u0DBD\u0DC0-\u0DC6\u0DE6-\u0DEF\u0E01-\u0E30\u0E32\u0E33\u0E40-\u0E46\u0E50-\u0E59\u0E81\u0E82\u0E84\u0E86-\u0E8A\u0E8C-\u0EA3\u0EA5\u0EA7-\u0EB0\u0EB2\u0EB3\u0EBD\u0EC0-\u0EC4\u0EC6\u0ED0-\u0ED9\u0EDC-\u0EDF\u0F00\u0F20-\u0F33\u0F40-\u0F47\u0F49-\u0F6C\u0F88-\u0F8C\u1000-\u102A\u103F-\u1049\u1050-\u1055\u105A-\u105D\u1061\u1065\u1066\u106E-\u1070\u1075-\u1081\u108E\u1090-\u1099\u10A0-\u10C5\u10C7\u10CD\u10D0-\u10FA\u10FC-\u1248\u124A-\u124D\u1250-\u1256\u1258\u125A-\u125D\u1260-\u1288\u128A-\u128D\u1290-\u12B0\u12B2-\u12B5\u12B8-\u12BE\u12C0\u12C2-\u12C5\u12C8-\u12D6\u12D8-\u1310\u1312-\u1315\u1318-\u135A\u1369-\u137C\u1380-\u138F\u13A0-\u13F5\u13F8-\u13FD\u1401-\u166C\u166F-\u167F\u1681-\u169A\u16A0-\u16EA\u16EE-\u16F8\u1700-\u1711\u171F-\u1731\u1740-\u1751\u1760-\u176C\u176E-\u1770\u1780-\u17B3\u17D7\u17DC\u17E0-\u17E9\u17F0-\u17F9\u1810-\u1819\u1820-\u1878\u1880-\u1884\u1887-\u18A8\u18AA\u18B0-\u18F5\u1900-\u191E\u1946-\u196D\u1970-\u1974\u1980-\u19AB\u19B0-\u19C9\u19D0-\u19DA\u1A00-\u1A16\u1A20-\u1A54\u1A80-\u1A89\u1A90-\u1A99\u1AA7\u1B05-\u1B33\u1B45-\u1B4C\u1B50-\u1B59\u1B83-\u1BA0\u1BAE-\u1BE5\u1C00-\u1C23\u1C40-\u1C49\u1C4D-\u1C7D\u1C80-\u1C88\u1C90-\u1CBA\u1CBD-\u1CBF\u1CE9-\u1CEC\u1CEE-\u1CF3\u1CF5\u1CF6\u1CFA\u1D00-\u1DBF\u1E00-\u1F15\u1F18-\u1F1D\u1F20-\u1F45\u1F48-\u1F4D\u1F50-\u1F57\u1F59\u1F5B\u1F5D\u1F5F-\u1F7D\u1F80-\u1FB4\u1FB6-\u1FBC\u1FBE\u1FC2-\u1FC4\u1FC6-\u1FCC\u1FD0-\u1FD3\u1FD6-\u1FDB\u1FE0-\u1FEC\u1FF2-\u1FF4\u1FF6-\u1FFC\u2070\u2071\u2074-\u2079\u207F-\u2089\u2090-\u209C\u2102\u2107\u210A-\u2113\u2115\u2119-\u211D\u2124\u2126\u2128\u212A-\u212D\u212F-\u2139\u213C-\u213F\u2145-\u2149\u214E\u2150-\u2189\u2460-\u249B\u24EA-\u24FF\u2776-\u2793\u2C00-\u2CE4\u2CEB-\u2CEE\u2CF2\u2CF3\u2CFD\u2D00-\u2D25\u2D27\u2D2D\u2D30-\u2D67\u2D6F\u2D80-\u2D96\u2DA0-\u2DA6\u2DA8-\u2DAE\u2DB0-\u2DB6\u2DB8-\u2DBE\u2DC0-\u2DC6\u2DC8-\u2DCE\u2DD0-\u2DD6\u2DD8-\u2DDE\u2E2F\u3005-\u3007\u3021-\u3029\u3031-\u3035\u3038-\u303C\u3041-\u3096\u309D-\u309F\u30A1-\u30FA\u30FC-\u30FF\u3105-\u312F\u3131-\u318E\u3192-\u3195\u31A0-\u31BF\u31F0-\u31FF\u3220-\u3229\u3248-\u324F\u3251-\u325F\u3280-\u3289\u32B1-\u32BF\u3400-\u4DBF\u4E00-\uA48C\uA4D0-\uA4FD\uA500-\uA60C\uA610-\uA62B\uA640-\uA66E\uA67F-\uA69D\uA6A0-\uA6EF\uA717-\uA71F\uA722-\uA788\uA78B-\uA7CA\uA7D0\uA7D1\uA7D3\uA7D5-\uA7D9\uA7F2-\uA801\uA803-\uA805\uA807-\uA80A\uA80C-\uA822\uA830-\uA835\uA840-\uA873\uA882-\uA8B3\uA8D0-\uA8D9\uA8F2-\uA8F7\uA8FB\uA8FD\uA8FE\uA900-\uA925\uA930-\uA946\uA960-\uA97C\uA984-\uA9B2\uA9CF-\uA9D9\uA9E0-\uA9E4\uA9E6-\uA9FE\uAA00-\uAA28\uAA40-\uAA42\uAA44-\uAA4B\uAA50-\uAA59\uAA60-\uAA76\uAA7A\uAA7E-\uAAAF\uAAB1\uAAB5\uAAB6\uAAB9-\uAABD\uAAC0\uAAC2\uAADB-\uAADD\uAAE0-\uAAEA\uAAF2-\uAAF4\uAB01-\uAB06\uAB09-\uAB0E\uAB11-\uAB16\uAB20-\uAB26\uAB28-\uAB2E\uAB30-\uAB5A\uAB5C-\uAB69\uAB70-\uABE2\uABF0-\uABF9\uAC00-\uD7A3\uD7B0-\uD7C6\uD7CB-\uD7FB\uF900-\uFA6D\uFA70-\uFAD9\uFB00-\uFB06\uFB13-\uFB17\uFB1D\uFB1F-\uFB28\uFB2A-\uFB36\uFB38-\uFB3C\uFB3E\uFB40\uFB41\uFB43\uFB44\uFB46-\uFBB1\uFBD3-\uFD3D\uFD50-\uFD8F\uFD92-\uFDC7\uFDF0-\uFDFB\uFE70-\uFE74\uFE76-\uFEFC\uFF10-\uFF19\uFF21-\uFF3A\uFF41-\uFF5A\uFF66-\uFFBE\uFFC2-\uFFC7\uFFCA-\uFFCF\uFFD2-\uFFD7\uFFDA-\uFFDC]|\uD800[\uDC00-\uDC0B\uDC0D-\uDC26\uDC28-\uDC3A\uDC3C\uDC3D\uDC3F-\uDC4D\uDC50-\uDC5D\uDC80-\uDCFA\uDD07-\uDD33\uDD40-\uDD78\uDD8A\uDD8B\uDE80-\uDE9C\uDEA0-\uDED0\uDEE1-\uDEFB\uDF00-\uDF23\uDF2D-\uDF4A\uDF50-\uDF75\uDF80-\uDF9D\uDFA0-\uDFC3\uDFC8-\uDFCF\uDFD1-\uDFD5]|\uD801[\uDC00-\uDC9D\uDCA0-\uDCA9\uDCB0-\uDCD3\uDCD8-\uDCFB\uDD00-\uDD27\uDD30-\uDD63\uDD70-\uDD7A\uDD7C-\uDD8A\uDD8C-\uDD92\uDD94\uDD95\uDD97-\uDDA1\uDDA3-\uDDB1\uDDB3-\uDDB9\uDDBB\uDDBC\uDE00-\uDF36\uDF40-\uDF55\uDF60-\uDF67\uDF80-\uDF85\uDF87-\uDFB0\uDFB2-\uDFBA]|\uD802[\uDC00-\uDC05\uDC08\uDC0A-\uDC35\uDC37\uDC38\uDC3C\uDC3F-\uDC55\uDC58-\uDC76\uDC79-\uDC9E\uDCA7-\uDCAF\uDCE0-\uDCF2\uDCF4\uDCF5\uDCFB-\uDD1B\uDD20-\uDD39\uDD80-\uDDB7\uDDBC-\uDDCF\uDDD2-\uDE00\uDE10-\uDE13\uDE15-\uDE17\uDE19-\uDE35\uDE40-\uDE48\uDE60-\uDE7E\uDE80-\uDE9F\uDEC0-\uDEC7\uDEC9-\uDEE4\uDEEB-\uDEEF\uDF00-\uDF35\uDF40-\uDF55\uDF58-\uDF72\uDF78-\uDF91\uDFA9-\uDFAF]|\uD803[\uDC00-\uDC48\uDC80-\uDCB2\uDCC0-\uDCF2\uDCFA-\uDD23\uDD30-\uDD39\uDE60-\uDE7E\uDE80-\uDEA9\uDEB0\uDEB1\uDF00-\uDF27\uDF30-\uDF45\uDF51-\uDF54\uDF70-\uDF81\uDFB0-\uDFCB\uDFE0-\uDFF6]|\uD804[\uDC03-\uDC37\uDC52-\uDC6F\uDC71\uDC72\uDC75\uDC83-\uDCAF\uDCD0-\uDCE8\uDCF0-\uDCF9\uDD03-\uDD26\uDD36-\uDD3F\uDD44\uDD47\uDD50-\uDD72\uDD76\uDD83-\uDDB2\uDDC1-\uDDC4\uDDD0-\uDDDA\uDDDC\uDDE1-\uDDF4\uDE00-\uDE11\uDE13-\uDE2B\uDE3F\uDE40\uDE80-\uDE86\uDE88\uDE8A-\uDE8D\uDE8F-\uDE9D\uDE9F-\uDEA8\uDEB0-\uDEDE\uDEF0-\uDEF9\uDF05-\uDF0C\uDF0F\uDF10\uDF13-\uDF28\uDF2A-\uDF30\uDF32\uDF33\uDF35-\uDF39\uDF3D\uDF50\uDF5D-\uDF61]|\uD805[\uDC00-\uDC34\uDC47-\uDC4A\uDC50-\uDC59\uDC5F-\uDC61\uDC80-\uDCAF\uDCC4\uDCC5\uDCC7\uDCD0-\uDCD9\uDD80-\uDDAE\uDDD8-\uDDDB\uDE00-\uDE2F\uDE44\uDE50-\uDE59\uDE80-\uDEAA\uDEB8\uDEC0-\uDEC9\uDF00-\uDF1A\uDF30-\uDF3B\uDF40-\uDF46]|\uD806[\uDC00-\uDC2B\uDCA0-\uDCF2\uDCFF-\uDD06\uDD09\uDD0C-\uDD13\uDD15\uDD16\uDD18-\uDD2F\uDD3F\uDD41\uDD50-\uDD59\uDDA0-\uDDA7\uDDAA-\uDDD0\uDDE1\uDDE3\uDE00\uDE0B-\uDE32\uDE3A\uDE50\uDE5C-\uDE89\uDE9D\uDEB0-\uDEF8]|\uD807[\uDC00-\uDC08\uDC0A-\uDC2E\uDC40\uDC50-\uDC6C\uDC72-\uDC8F\uDD00-\uDD06\uDD08\uDD09\uDD0B-\uDD30\uDD46\uDD50-\uDD59\uDD60-\uDD65\uDD67\uDD68\uDD6A-\uDD89\uDD98\uDDA0-\uDDA9\uDEE0-\uDEF2\uDF02\uDF04-\uDF10\uDF12-\uDF33\uDF50-\uDF59\uDFB0\uDFC0-\uDFD4]|\uD808[\uDC00-\uDF99]|\uD809[\uDC00-\uDC6E\uDC80-\uDD43]|\uD80B[\uDF90-\uDFF0]|[\uD80C\uD81C-\uD820\uD822\uD840-\uD868\uD86A-\uD86C\uD86F-\uD872\uD874-\uD879\uD880-\uD883\uD885-\uD887][\uDC00-\uDFFF]|\uD80D[\uDC00-\uDC2F\uDC41-\uDC46]|\uD811[\uDC00-\uDE46]|\uD81A[\uDC00-\uDE38\uDE40-\uDE5E\uDE60-\uDE69\uDE70-\uDEBE\uDEC0-\uDEC9\uDED0-\uDEED\uDF00-\uDF2F\uDF40-\uDF43\uDF50-\uDF59\uDF5B-\uDF61\uDF63-\uDF77\uDF7D-\uDF8F]|\uD81B[\uDE40-\uDE96\uDF00-\uDF4A\uDF50\uDF93-\uDF9F\uDFE0\uDFE1\uDFE3]|\uD821[\uDC00-\uDFF7]|\uD823[\uDC00-\uDCD5\uDD00-\uDD08]|\uD82B[\uDFF0-\uDFF3\uDFF5-\uDFFB\uDFFD\uDFFE]|\uD82C[\uDC00-\uDD22\uDD32\uDD50-\uDD52\uDD55\uDD64-\uDD67\uDD70-\uDEFB]|\uD82F[\uDC00-\uDC6A\uDC70-\uDC7C\uDC80-\uDC88\uDC90-\uDC99]|\uD834[\uDEC0-\uDED3\uDEE0-\uDEF3\uDF60-\uDF78]|\uD835[\uDC00-\uDC54\uDC56-\uDC9C\uDC9E\uDC9F\uDCA2\uDCA5\uDCA6\uDCA9-\uDCAC\uDCAE-\uDCB9\uDCBB\uDCBD-\uDCC3\uDCC5-\uDD05\uDD07-\uDD0A\uDD0D-\uDD14\uDD16-\uDD1C\uDD1E-\uDD39\uDD3B-\uDD3E\uDD40-\uDD44\uDD46\uDD4A-\uDD50\uDD52-\uDEA5\uDEA8-\uDEC0\uDEC2-\uDEDA\uDEDC-\uDEFA\uDEFC-\uDF14\uDF16-\uDF34\uDF36-\uDF4E\uDF50-\uDF6E\uDF70-\uDF88\uDF8A-\uDFA8\uDFAA-\uDFC2\uDFC4-\uDFCB\uDFCE-\uDFFF]|\uD837[\uDF00-\uDF1E\uDF25-\uDF2A]|\uD838[\uDC30-\uDC6D\uDD00-\uDD2C\uDD37-\uDD3D\uDD40-\uDD49\uDD4E\uDE90-\uDEAD\uDEC0-\uDEEB\uDEF0-\uDEF9]|\uD839[\uDCD0-\uDCEB\uDCF0-\uDCF9\uDFE0-\uDFE6\uDFE8-\uDFEB\uDFED\uDFEE\uDFF0-\uDFFE]|\uD83A[\uDC00-\uDCC4\uDCC7-\uDCCF\uDD00-\uDD43\uDD4B\uDD50-\uDD59]|\uD83B[\uDC71-\uDCAB\uDCAD-\uDCAF\uDCB1-\uDCB4\uDD01-\uDD2D\uDD2F-\uDD3D\uDE00-\uDE03\uDE05-\uDE1F\uDE21\uDE22\uDE24\uDE27\uDE29-\uDE32\uDE34-\uDE37\uDE39\uDE3B\uDE42\uDE47\uDE49\uDE4B\uDE4D-\uDE4F\uDE51\uDE52\uDE54\uDE57\uDE59\uDE5B\uDE5D\uDE5F\uDE61\uDE62\uDE64\uDE67-\uDE6A\uDE6C-\uDE72\uDE74-\uDE77\uDE79-\uDE7C\uDE7E\uDE80-\uDE89\uDE8B-\uDE9B\uDEA1-\uDEA3\uDEA5-\uDEA9\uDEAB-\uDEBB]|\uD83C[\uDD00-\uDD0C]|\uD83E[\uDFF0-\uDFF9]|\uD869[\uDC00-\uDEDF\uDF00-\uDFFF]|\uD86D[\uDC00-\uDF39\uDF40-\uDFFF]|\uD86E[\uDC00-\uDC1D\uDC20-\uDFFF]|\uD873[\uDC00-\uDEA1\uDEB0-\uDFFF]|\uD87A[\uDC00-\uDFE0]|\uD87E[\uDC00-\uDE1D]|\uD884[\uDC00-\uDF4A\uDF50-\uDFFF]|\uD888[\uDC00-\uDFAF])/))) {
                var r = n[1] || n[2] || "";
                if (!r || "" === t || this.rules.inline.punctuation.exec(t)) {
                    var i = n[0].length - 1
                      , s = i
                      , a = 0
                      , l = "*" === n[0][0] ? this.rules.inline.emStrong.rDelimAst : this.rules.inline.emStrong.rDelimUnd;
                    for (l.lastIndex = 0,
                    u = u.slice(-1 * e.length + i); null != (n = l.exec(u)); ) {
                        var o, D = n[1] || n[2] || n[3] || n[4] || n[5] || n[6];
                        if (D)
                            if (D = D.length,
                            n[3] || n[4])
                                s += D;
                            else if ((n[5] || n[6]) && i % 3 && !((i + D) % 3))
                                a += D;
                            else if (!(0 < (s -= D)))
                                return D = Math.min(D, D + s + a),
                                o = e.slice(0, i + n.index + D + 1),
                                Math.min(i, D) % 2 ? (D = o.slice(1, -1),
                                {
                                    type: "em",
                                    raw: o,
                                    text: D,
                                    tokens: this.lexer.inlineTokens(D)
                                }) : (D = o.slice(2, -2),
                                {
                                    type: "strong",
                                    raw: o,
                                    text: D,
                                    tokens: this.lexer.inlineTokens(D)
                                })
                    }
                }
            }
        }
        ,
        u.codespan = function(e) {
            var u, t, n, e = this.rules.inline.code.exec(e);
            if (e)
                return n = e[2].replace(/\n/g, " "),
                u = /[^ ]/.test(n),
                t = /^ /.test(n) && / $/.test(n),
                n = d(n = u && t ? n.substring(1, n.length - 1) : n, !0),
                {
                    type: "codespan",
                    raw: e[0],
                    text: n
                }
        }
        ,
        u.br = function(e) {
            e = this.rules.inline.br.exec(e);
            if (e)
                return {
                    type: "br",
                    raw: e[0]
                }
        }
        ,
        u.del = function(e) {
            e = this.rules.inline.del.exec(e);
            if (e)
                return {
                    type: "del",
                    raw: e[0],
                    text: e[2],
                    tokens: this.lexer.inlineTokens(e[2])
                }
        }
        ,
        u.autolink = function(e, u) {
            var t, e = this.rules.inline.autolink.exec(e);
            if (e)
                return u = "@" === e[2] ? "mailto:" + (t = d(this.options.mangle ? u(e[1]) : e[1])) : t = d(e[1]),
                {
                    type: "link",
                    raw: e[0],
                    text: t,
                    href: u,
                    tokens: [{
                        type: "text",
                        raw: t,
                        text: t
                    }]
                }
        }
        ,
        u.url = function(e, u) {
            var t, n, r, i;
            if (t = this.rules.inline.url.exec(e)) {
                if ("@" === t[2])
                    r = "mailto:" + (n = d(this.options.mangle ? u(t[0]) : t[0]));
                else {
                    for (; i = t[0],
                    t[0] = this.rules.inline._backpedal.exec(t[0])[0],
                    i !== t[0]; )
                        ;
                    n = d(t[0]),
                    r = "www." === t[1] ? "http://" + t[0] : t[0]
                }
                return {
                    type: "link",
                    raw: t[0],
                    text: n,
                    href: r,
                    tokens: [{
                        type: "text",
                        raw: n,
                        text: n
                    }]
                }
            }
        }
        ,
        u.inlineText = function(e, u) {
            e = this.rules.inline.text.exec(e);
            if (e)
                return u = this.lexer.state.inRawBlock ? this.options.sanitize ? this.options.sanitizer ? this.options.sanitizer(e[0]) : d(e[0]) : e[0] : d(this.options.smartypants ? u(e[0]) : e[0]),
                {
                    type: "text",
                    raw: e[0],
                    text: u
                }
        }
        ,
        e
    }()
      , B = {
        newline: /^(?: *(?:\n|$))+/,
        code: /^( {4}[^\n]+(?:\n(?: *(?:\n|$))*)?)+/,
        fences: /^ {0,3}(`{3,}(?=[^`\n]*(?:\n|$))|~{3,})([^\n]*)(?:\n|$)(?:|([\s\S]*?)(?:\n|$))(?: {0,3}\1[~`]* *(?=\n|$)|$)/,
        hr: /^ {0,3}((?:-[\t ]*){3,}|(?:_[ \t]*){3,}|(?:\*[ \t]*){3,})(?:\n+|$)/,
        heading: /^ {0,3}(#{1,6})(?=\s|$)(.*)(?:\n+|$)/,
        blockquote: /^( {0,3}> ?(paragraph|[^\n]*)(?:\n|$))+/,
        list: /^( {0,3}bull)([ \t][^\n]+?)?(?:\n|$)/,
        html: "^ {0,3}(?:<(script|pre|style|textarea)[\\s>][\\s\\S]*?(?:</\\1>[^\\n]*\\n+|$)|comment[^\\n]*(\\n+|$)|<\\?[\\s\\S]*?(?:\\?>\\n*|$)|<![A-Z][\\s\\S]*?(?:>\\n*|$)|<!\\[CDATA\\[[\\s\\S]*?(?:\\]\\]>\\n*|$)|</?(tag)(?: +|\\n|/?>)[\\s\\S]*?(?:(?:\\n *)+\\n|$)|<(?!script|pre|style|textarea)([a-z][\\w-]*)(?:attribute)*? */?>(?=[ \\t]*(?:\\n|$))[\\s\\S]*?(?:(?:\\n *)+\\n|$)|</(?!script|pre|style|textarea)[a-z][\\w-]*\\s*>(?=[ \\t]*(?:\\n|$))[\\s\\S]*?(?:(?:\\n *)+\\n|$))",
        def: /^ {0,3}\[(label)\]: *(?:\n *)?([^<\s][^\s]*|<.*?>)(?:(?: +(?:\n *)?| *\n *)(title))? *(?:\n+|$)/,
        table: C,
        lheading: /^((?:(?!^bull ).|\n(?!\n|bull ))+?)\n {0,3}(=+|-+) *(?:\n+|$)/,
        _paragraph: /^([^\n]+(?:\n(?!hr|heading|lheading|blockquote|fences|list|html|table| +\n)[^\n]+)*)/,
        text: /^[^\n]+/,
        _label: /(?!\s*\])(?:\\.|[^\[\]\\])+/,
        _title: /(?:"(?:\\"?|[^"\\])*"|'[^'\n]*(?:\n[^'\n]+)*\n?'|\([^()]*\))/
    }
      , w = (B.def = h(B.def).replace("label", B._label).replace("title", B._title).getRegex(),
    B.bullet = /(?:[*+-]|\d{1,9}[.)])/,
    B.listItemStart = h(/^( *)(bull) */).replace("bull", B.bullet).getRegex(),
    B.list = h(B.list).replace(/bull/g, B.bullet).replace("hr", "\\n+(?=\\1?(?:(?:- *){3,}|(?:_ *){3,}|(?:\\* *){3,})(?:\\n+|$))").replace("def", "\\n+(?=" + B.def.source + ")").getRegex(),
    B._tag = "address|article|aside|base|basefont|blockquote|body|caption|center|col|colgroup|dd|details|dialog|dir|div|dl|dt|fieldset|figcaption|figure|footer|form|frame|frameset|h[1-6]|head|header|hr|html|iframe|legend|li|link|main|menu|menuitem|meta|nav|noframes|ol|optgroup|option|p|param|section|source|summary|table|tbody|td|tfoot|th|thead|title|tr|track|ul",
    B._comment = /<!--(?!-?>)[\s\S]*?(?:-->|$)/,
    B.html = h(B.html, "i").replace("comment", B._comment).replace("tag", B._tag).replace("attribute", / +[a-zA-Z:_][\w.:-]*(?: *= *"[^"\n]*"| *= *'[^'\n]*'| *= *[^\s"'=<>`]+)?/).getRegex(),
    B.lheading = h(B.lheading).replace(/bull/g, B.bullet).getRegex(),
    B.paragraph = h(B._paragraph).replace("hr", B.hr).replace("heading", " {0,3}#{1,6} ").replace("|lheading", "").replace("|table", "").replace("blockquote", " {0,3}>").replace("fences", " {0,3}(?:`{3,}(?=[^`\\n]*\\n)|~{3,})[^\\n]*\\n").replace("list", " {0,3}(?:[*+-]|1[.)]) ").replace("html", "</?(?:tag)(?: +|\\n|/?>)|<(?:script|pre|style|textarea|!--)").replace("tag", B._tag).getRegex(),
    B.blockquote = h(B.blockquote).replace("paragraph", B.paragraph).getRegex(),
    B.normal = A({}, B),
    B.gfm = A({}, B.normal, {
        table: "^ *([^\\n ].*\\|.*)\\n {0,3}(?:\\| *)?(:?-+:? *(?:\\| *:?-+:? *)*)(?:\\| *)?(?:\\n((?:(?! *\\n|hr|heading|blockquote|code|fences|list|html).*(?:\\n|$))*)\\n*|$)"
    }),
    B.gfm.table = h(B.gfm.table).replace("hr", B.hr).replace("heading", " {0,3}#{1,6} ").replace("blockquote", " {0,3}>").replace("code", " {4}[^\\n]").replace("fences", " {0,3}(?:`{3,}(?=[^`\\n]*\\n)|~{3,})[^\\n]*\\n").replace("list", " {0,3}(?:[*+-]|1[.)]) ").replace("html", "</?(?:tag)(?: +|\\n|/?>)|<(?:script|pre|style|textarea|!--)").replace("tag", B._tag).getRegex(),
    B.gfm.paragraph = h(B._paragraph).replace("hr", B.hr).replace("heading", " {0,3}#{1,6} ").replace("|lheading", "").replace("table", B.gfm.table).replace("blockquote", " {0,3}>").replace("fences", " {0,3}(?:`{3,}(?=[^`\\n]*\\n)|~{3,})[^\\n]*\\n").replace("list", " {0,3}(?:[*+-]|1[.)]) ").replace("html", "</?(?:tag)(?: +|\\n|/?>)|<(?:script|pre|style|textarea|!--)").replace("tag", B._tag).getRegex(),
    B.pedantic = A({}, B.normal, {
        html: h("^ *(?:comment *(?:\\n|\\s*$)|<(tag)[\\s\\S]+?</\\1> *(?:\\n{2,}|\\s*$)|<tag(?:\"[^\"]*\"|'[^']*'|\\s[^'\"/>\\s]*)*?/?> *(?:\\n{2,}|\\s*$))").replace("comment", B._comment).replace(/tag/g, "(?!(?:a|em|strong|small|s|cite|q|dfn|abbr|data|time|code|var|samp|kbd|sub|sup|i|b|u|mark|ruby|rt|rp|bdi|bdo|span|br|wbr|ins|del|img)\\b)\\w+(?!:|[^\\w\\s@]*@)\\b").getRegex(),
        def: /^ *\[([^\]]+)\]: *<?([^\s>]+)>?(?: +(["(][^\n]+[")]))? *(?:\n+|$)/,
        heading: /^(#{1,6})(.*)(?:\n+|$)/,
        fences: C,
        lheading: /^(.+?)\n {0,3}(=+|-+) *(?:\n+|$)/,
        paragraph: h(B.normal._paragraph).replace("hr", B.hr).replace("heading", " *#{1,6} *[^\n]").replace("lheading", B.lheading).replace("blockquote", " {0,3}>").replace("|fences", "").replace("|list", "").replace("|html", "").getRegex()
    }),
    {
        escape: /^\\([!"#$%&'()*+,\-./:;<=>?@\[\]\\^_`{|}~])/,
        autolink: /^<(scheme:[^\s\x00-\x1f<>]*|email)>/,
        url: C,
        tag: "^comment|^</[a-zA-Z][\\w:-]*\\s*>|^<[a-zA-Z][\\w-]*(?:attribute)*?\\s*/?>|^<\\?[\\s\\S]*?\\?>|^<![a-zA-Z]+\\s[\\s\\S]*?>|^<!\\[CDATA\\[[\\s\\S]*?\\]\\]>",
        link: /^!?\[(label)\]\(\s*(href)(?:\s+(title))?\s*\)/,
        reflink: /^!?\[(label)\]\[(ref)\]/,
        nolink: /^!?\[(ref)\](?:\[\])?/,
        reflinkSearch: "reflink|nolink(?!\\()",
        emStrong: {
            lDelim: /^(?:\*+(?:([punct_])|[^\s*]))|^_+(?:([punct*])|([^\s_]))/,
            rDelimAst: /^[^_*]*?\_\_[^_*]*?\*[^_*]*?(?=\_\_)|[^*]+(?=[^*])|[punct_](\*+)(?=[\s]|$)|[^punct*_\s](\*+)(?=[punct_\s]|$)|[punct_\s](\*+)(?=[^punct*_\s])|[\s](\*+)(?=[punct_])|[punct_](\*+)(?=[punct_])|[^punct*_\s](\*+)(?=[^punct*_\s])/,
            rDelimUnd: /^[^_*]*?\*\*[^_*]*?\_[^_*]*?(?=\*\*)|[^_]+(?=[^_])|[punct*](\_+)(?=[\s]|$)|[^punct*_\s](\_+)(?=[punct*\s]|$)|[punct*\s](\_+)(?=[^punct*_\s])|[\s](\_+)(?=[punct*])|[punct*](\_+)(?=[punct*])/
        },
        code: /^(`+)([^`]|[^`][\s\S]*?[^`])\1(?!`)/,
        br: /^( {2,}|\\)\n(?!\s*$)/,
        del: C,
        text: /^(`+|[^`])(?:(?= {2,}\n)|[\s\S]*?(?:(?=[\\<!\[`*_]|\b_|$)|[^ ](?= {2,}\n)))/,
        punctuation: /^([\spunctuation])/
    });
    function q(e) {
        return e.replace(/---/g, "—").replace(/--/g, "–").replace(/(^|[-\u2014/(\[{"\s])'/g, "$1‘").replace(/'/g, "’").replace(/(^|[-\u2014/(\[{\u2018\s])"/g, "$1“").replace(/"/g, "”").replace(/\.{3}/g, "…")
    }
    function v(e) {
        for (var u, t = "", n = e.length, r = 0; r < n; r++)
            u = e.charCodeAt(r),
            t += "&#" + (u = .5 < Math.random() ? "x" + u.toString(16) : u) + ";";
        return t
    }
    w._uc_punctuation = "\\u00A1\\u00A7\\u00AB\\u00B6\\u00B7\\u00BB\\u00BF\\u037E\\u0387\\u055A-\\u055F\\u0589\\u058A\\u05BE\\u05C0\\u05C3\\u05C6\\u05F3\\u05F4\\u0609\\u060A\\u060C\\u060D\\u061B\\u061E\\u061F\\u066A-\\u066D\\u06D4\\u0700-\\u070D\\u07F7-\\u07F9\\u0830-\\u083E\\u085E\\u0964\\u0965\\u0970\\u0AF0\\u0DF4\\u0E4F\\u0E5A\\u0E5B\\u0F04-\\u0F12\\u0F14\\u0F3A-\\u0F3D\\u0F85\\u0FD0-\\u0FD4\\u0FD9\\u0FDA\\u104A-\\u104F\\u10FB\\u1360-\\u1368\\u1400\\u166D\\u166E\\u169B\\u169C\\u16EB-\\u16ED\\u1735\\u1736\\u17D4-\\u17D6\\u17D8-\\u17DA\\u1800-\\u180A\\u1944\\u1945\\u1A1E\\u1A1F\\u1AA0-\\u1AA6\\u1AA8-\\u1AAD\\u1B5A-\\u1B60\\u1BFC-\\u1BFF\\u1C3B-\\u1C3F\\u1C7E\\u1C7F\\u1CC0-\\u1CC7\\u1CD3\\u2010-\\u2027\\u2030-\\u2043\\u2045-\\u2051\\u2053-\\u205E\\u207D\\u207E\\u208D\\u208E\\u2308-\\u230B\\u2329\\u232A\\u2768-\\u2775\\u27C5\\u27C6\\u27E6-\\u27EF\\u2983-\\u2998\\u29D8-\\u29DB\\u29FC\\u29FD\\u2CF9-\\u2CFC\\u2CFE\\u2CFF\\u2D70\\u2E00-\\u2E2E\\u2E30-\\u2E42\\u3001-\\u3003\\u3008-\\u3011\\u3014-\\u301F\\u3030\\u303D\\u30A0\\u30FB\\uA4FE\\uA4FF\\uA60D-\\uA60F\\uA673\\uA67E\\uA6F2-\\uA6F7\\uA874-\\uA877\\uA8CE\\uA8CF\\uA8F8-\\uA8FA\\uA8FC\\uA92E\\uA92F\\uA95F\\uA9C1-\\uA9CD\\uA9DE\\uA9DF\\uAA5C-\\uAA5F\\uAADE\\uAADF\\uAAF0\\uAAF1\\uABEB\\uFD3E\\uFD3F\\uFE10-\\uFE19\\uFE30-\\uFE52\\uFE54-\\uFE61\\uFE63\\uFE68\\uFE6A\\uFE6B\\uFF01-\\uFF03\\uFF05-\\uFF0A\\uFF0C-\\uFF0F\\uFF1A\\uFF1B\\uFF1F\\uFF20\\uFF3B-\\uFF3D\\uFF3F\\uFF5B\\uFF5D\\uFF5F-\\uFF65",
    w._punctuation = "!\"#$%&'()+\\-.,/:;<=>?@\\[\\]`^{|}~\\\\" + w._uc_punctuation,
    w.punctuation = h(w.punctuation).replace(/punctuation/g, w._punctuation).getRegex(),
    w.blockSkip = /\[[^[\]]*?\]\([^\(\)]*?\)|`[^`]*?`|<[^<>]*?>/g,
    w.escapedPunct = /\\[punct_*]/g,
    w._comment = h(B._comment).replace("(?:--\x3e|$)", "--\x3e").getRegex(),
    w.emStrong.lDelim = h(w.emStrong.lDelim).replace(/punct/g, w._punctuation).getRegex(),
    w.emStrong.rDelimAst = h(w.emStrong.rDelimAst, "g").replace(/punct/g, w._punctuation).getRegex(),
    w.emStrong.rDelimUnd = h(w.emStrong.rDelimUnd, "g").replace(/punct/g, w._punctuation).getRegex(),
    w.escapedPunct = h(w.escapedPunct, "g").replace(/punct/g, w._punctuation).getRegex(),
    w._escapes = /\\([!"#$%&'()*+,\-./:;<=>?@\[\]\\^_`{|}~])/g,
    w._scheme = /[a-zA-Z][a-zA-Z0-9+.-]{1,31}/,
    w._email = /[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+(@)[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+(?![-_])/,
    w.autolink = h(w.autolink).replace("scheme", w._scheme).replace("email", w._email).getRegex(),
    w._attribute = /\s+[a-zA-Z:_][\w.:-]*(?:\s*=\s*"[^"]*"|\s*=\s*'[^']*'|\s*=\s*[^\s"'=<>`]+)?/,
    w.tag = h(w.tag).replace("comment", w._comment).replace("attribute", w._attribute).getRegex(),
    w._label = /(?:\[(?:\\.|[^\[\]\\])*\]|\\.|`[^`]*`|[^\[\]\\`])*?/,
    w._href = /<(?:\\.|[^\n<>\\])+>|[^\s\x00-\x1f]*/,
    w._title = /"(?:\\"?|[^"\\])*"|'(?:\\'?|[^'\\])*'|\((?:\\\)?|[^)\\])*\)/,
    w.link = h(w.link).replace("label", w._label).replace("href", w._href).replace("title", w._title).getRegex(),
    w.reflink = h(w.reflink).replace("label", w._label).replace("ref", B._label).getRegex(),
    w.nolink = h(w.nolink).replace("ref", B._label).getRegex(),
    w.reflinkSearch = h(w.reflinkSearch, "g").replace("reflink", w.reflink).replace("nolink", w.nolink).getRegex(),
    w.normal = A({}, w),
    w.pedantic = A({}, w.normal, {
        strong: {
            start: /^__|\*\*/,
            middle: /^__(?=\S)([\s\S]*?\S)__(?!_)|^\*\*(?=\S)([\s\S]*?\S)\*\*(?!\*)/,
            endAst: /\*\*(?!\*)/g,
            endUnd: /__(?!_)/g
        },
        em: {
            start: /^_|\*/,
            middle: /^()\*(?=\S)([\s\S]*?\S)\*(?!\*)|^_(?=\S)([\s\S]*?\S)_(?!_)/,
            endAst: /\*(?!\*)/g,
            endUnd: /_(?!_)/g
        },
        link: h(/^!?\[(label)\]\((.*?)\)/).replace("label", w._label).getRegex(),
        reflink: h(/^!?\[(label)\]\s*\[([^\]]*)\]/).replace("label", w._label).getRegex()
    }),
    w.gfm = A({}, w.normal, {
        escape: h(w.escape).replace("])", "~|])").getRegex(),
        _extended_email: /[A-Za-z0-9._+-]+(@)[a-zA-Z0-9-_]+(?:\.[a-zA-Z0-9-_]*[a-zA-Z0-9])+(?![-_])/,
        url: /^((?:ftp|https?):\/\/|www\.)(?:[a-zA-Z0-9\-]+\.?)+[^\s<]*|^email/,
        _backpedal: /(?:[^?!.,:;*_'"~()&]+|\([^)]*\)|&(?![a-zA-Z0-9]+;$)|[?!.,:;*_'"~)]+(?!$))+/,
        del: /^(~~?)(?=[^\s~])([\s\S]*?[^\s~])\1(?=[^~]|$)/,
        text: /^([`~]+|[^`~])(?:(?= {2,}\n)|(?=[a-zA-Z0-9.!#$%&'*+\/=?_`{\|}~-]+@)|[\s\S]*?(?:(?=[\\<!\[`*~_]|\b_|https?:\/\/|ftp:\/\/|www\.|$)|[^ ](?= {2,}\n)|[^a-zA-Z0-9.!#$%&'*+\/=?_`{\|}~-](?=[a-zA-Z0-9.!#$%&'*+\/=?_`{\|}~-]+@)))/
    }),
    w.gfm.url = h(w.gfm.url, "i").replace("email", w.gfm._extended_email).getRegex(),
    w.breaks = A({}, w.gfm, {
        br: h(w.br).replace("{2,}", "*").getRegex(),
        text: h(w.gfm.text).replace("\\b_", "\\b_| {2,}\\n").replace(/\{2,\}/g, "*").getRegex()
    });
    var y = function() {
        function t(e) {
            this.tokens = [],
            this.tokens.links = Object.create(null),
            this.options = e || r.defaults,
            this.options.tokenizer = this.options.tokenizer || new b,
            this.tokenizer = this.options.tokenizer,
            this.tokenizer.options = this.options,
            (this.tokenizer.lexer = this).inlineQueue = [],
            this.state = {
                inLink: !1,
                inRawBlock: !1,
                top: !0
            };
            e = {
                block: B.normal,
                inline: w.normal
            };
            this.options.pedantic ? (e.block = B.pedantic,
            e.inline = w.pedantic) : this.options.gfm && (e.block = B.gfm,
            this.options.breaks ? e.inline = w.breaks : e.inline = w.gfm),
            this.tokenizer.rules = e
        }
        t.lex = function(e, u) {
            return new t(u).lex(e)
        }
        ,
        t.lexInline = function(e, u) {
            return new t(u).inlineTokens(e)
        }
        ;
        var e, u, n = t.prototype;
        return n.lex = function(e) {
            var u;
            for (e = e.replace(/\r\n|\r/g, "\n"),
            this.blockTokens(e, this.tokens); u = this.inlineQueue.shift(); )
                this.inlineTokens(u.src, u.tokens);
            return this.tokens
        }
        ,
        n.blockTokens = function(r, i) {
            var s, a, l, o, D = this;
            for (void 0 === i && (i = []),
            r = this.options.pedantic ? r.replace(/\t/g, "    ").replace(/^ +$/gm, "") : r.replace(/^( *)(\t+)/gm, function(e, u, t) {
                return u + "    ".repeat(t.length)
            }); r; ) {
                var e = function() {
                    if (D.options.extensions && D.options.extensions.block && D.options.extensions.block.some(function(e) {
                        return !!(s = e.call({
                            lexer: D
                        }, r, i)) && (r = r.substring(s.raw.length),
                        i.push(s),
                        !0)
                    }))
                        return "continue";
                    if (s = D.tokenizer.space(r))
                        return r = r.substring(s.raw.length),
                        1 === s.raw.length && 0 < i.length ? i[i.length - 1].raw += "\n" : i.push(s),
                        "continue";
                    if (s = D.tokenizer.code(r))
                        return r = r.substring(s.raw.length),
                        !(a = i[i.length - 1]) || "paragraph" !== a.type && "text" !== a.type ? i.push(s) : (a.raw += "\n" + s.raw,
                        a.text += "\n" + s.text,
                        D.inlineQueue[D.inlineQueue.length - 1].src = a.text),
                        "continue";
                    if (s = D.tokenizer.fences(r))
                        return r = r.substring(s.raw.length),
                        i.push(s),
                        "continue";
                    if (s = D.tokenizer.heading(r))
                        return r = r.substring(s.raw.length),
                        i.push(s),
                        "continue";
                    if (s = D.tokenizer.hr(r))
                        return r = r.substring(s.raw.length),
                        i.push(s),
                        "continue";
                    if (s = D.tokenizer.blockquote(r))
                        return r = r.substring(s.raw.length),
                        i.push(s),
                        "continue";
                    if (s = D.tokenizer.list(r))
                        return r = r.substring(s.raw.length),
                        i.push(s),
                        "continue";
                    if (s = D.tokenizer.html(r))
                        return r = r.substring(s.raw.length),
                        i.push(s),
                        "continue";
                    if (s = D.tokenizer.def(r))
                        return r = r.substring(s.raw.length),
                        !(a = i[i.length - 1]) || "paragraph" !== a.type && "text" !== a.type ? D.tokens.links[s.tag] || (D.tokens.links[s.tag] = {
                            href: s.href,
                            title: s.title
                        }) : (a.raw += "\n" + s.raw,
                        a.text += "\n" + s.raw,
                        D.inlineQueue[D.inlineQueue.length - 1].src = a.text),
                        "continue";
                    if (s = D.tokenizer.table(r))
                        return r = r.substring(s.raw.length),
                        i.push(s),
                        "continue";
                    if (s = D.tokenizer.lheading(r))
                        return r = r.substring(s.raw.length),
                        i.push(s),
                        "continue";
                    var u, t, n;
                    if (l = r,
                    D.options.extensions && D.options.extensions.startBlock && (u = 1 / 0,
                    t = r.slice(1),
                    D.options.extensions.startBlock.forEach(function(e) {
                        "number" == typeof (n = e.call({
                            lexer: this
                        }, t)) && 0 <= n && (u = Math.min(u, n))
                    }),
                    u < 1 / 0) && 0 <= u && (l = r.substring(0, u + 1)),
                    D.state.top && (s = D.tokenizer.paragraph(l)))
                        return a = i[i.length - 1],
                        o && "paragraph" === a.type ? (a.raw += "\n" + s.raw,
                        a.text += "\n" + s.text,
                        D.inlineQueue.pop(),
                        D.inlineQueue[D.inlineQueue.length - 1].src = a.text) : i.push(s),
                        o = l.length !== r.length,
                        r = r.substring(s.raw.length),
                        "continue";
                    if (s = D.tokenizer.text(r))
                        return r = r.substring(s.raw.length),
                        (a = i[i.length - 1]) && "text" === a.type ? (a.raw += "\n" + s.raw,
                        a.text += "\n" + s.text,
                        D.inlineQueue.pop(),
                        D.inlineQueue[D.inlineQueue.length - 1].src = a.text) : i.push(s),
                        "continue";
                    if (r) {
                        var e = "Infinite loop on byte: " + r.charCodeAt(0);
                        if (D.options.silent)
                            return console.error(e),
                            "break";
                        throw new Error(e)
                    }
                }();
                if ("continue" !== e && "break" === e)
                    break
            }
            return this.state.top = !0,
            i
        }
        ,
        n.inline = function(e, u) {
            return this.inlineQueue.push({
                src: e,
                tokens: u = void 0 === u ? [] : u
            }),
            u
        }
        ,
        n.inlineTokens = function(r, i) {
            var s, a, l, e, o, D, c = this, p = (void 0 === i && (i = []),
            r);
            if (this.tokens.links) {
                var u = Object.keys(this.tokens.links);
                if (0 < u.length)
                    for (; null != (e = this.tokenizer.rules.inline.reflinkSearch.exec(p)); )
                        u.includes(e[0].slice(e[0].lastIndexOf("[") + 1, -1)) && (p = p.slice(0, e.index) + "[" + "a".repeat(e[0].length - 2) + "]" + p.slice(this.tokenizer.rules.inline.reflinkSearch.lastIndex))
            }
            for (; null != (e = this.tokenizer.rules.inline.blockSkip.exec(p)); )
                p = p.slice(0, e.index) + "[" + "a".repeat(e[0].length - 2) + "]" + p.slice(this.tokenizer.rules.inline.blockSkip.lastIndex);
            for (; null != (e = this.tokenizer.rules.inline.escapedPunct.exec(p)); )
                p = p.slice(0, e.index) + "++" + p.slice(this.tokenizer.rules.inline.escapedPunct.lastIndex);
            for (; r; ) {
                var t = function() {
                    if (o || (D = ""),
                    o = !1,
                    c.options.extensions && c.options.extensions.inline && c.options.extensions.inline.some(function(e) {
                        return !!(s = e.call({
                            lexer: c
                        }, r, i)) && (r = r.substring(s.raw.length),
                        i.push(s),
                        !0)
                    }))
                        return "continue";
                    if (s = c.tokenizer.escape(r))
                        return r = r.substring(s.raw.length),
                        i.push(s),
                        "continue";
                    if (s = c.tokenizer.tag(r))
                        return r = r.substring(s.raw.length),
                        (a = i[i.length - 1]) && "text" === s.type && "text" === a.type ? (a.raw += s.raw,
                        a.text += s.text) : i.push(s),
                        "continue";
                    if (s = c.tokenizer.link(r))
                        return r = r.substring(s.raw.length),
                        i.push(s),
                        "continue";
                    if (s = c.tokenizer.reflink(r, c.tokens.links))
                        return r = r.substring(s.raw.length),
                        (a = i[i.length - 1]) && "text" === s.type && "text" === a.type ? (a.raw += s.raw,
                        a.text += s.text) : i.push(s),
                        "continue";
                    if (s = c.tokenizer.emStrong(r, p, D))
                        return r = r.substring(s.raw.length),
                        i.push(s),
                        "continue";
                    if (s = c.tokenizer.codespan(r))
                        return r = r.substring(s.raw.length),
                        i.push(s),
                        "continue";
                    if (s = c.tokenizer.br(r))
                        return r = r.substring(s.raw.length),
                        i.push(s),
                        "continue";
                    if (s = c.tokenizer.del(r))
                        return r = r.substring(s.raw.length),
                        i.push(s),
                        "continue";
                    if (s = c.tokenizer.autolink(r, v))
                        return r = r.substring(s.raw.length),
                        i.push(s),
                        "continue";
                    if (!c.state.inLink && (s = c.tokenizer.url(r, v)))
                        return r = r.substring(s.raw.length),
                        i.push(s),
                        "continue";
                    var u, t, n;
                    if (l = r,
                    c.options.extensions && c.options.extensions.startInline && (u = 1 / 0,
                    t = r.slice(1),
                    c.options.extensions.startInline.forEach(function(e) {
                        "number" == typeof (n = e.call({
                            lexer: this
                        }, t)) && 0 <= n && (u = Math.min(u, n))
                    }),
                    u < 1 / 0) && 0 <= u && (l = r.substring(0, u + 1)),
                    s = c.tokenizer.inlineText(l, q))
                        return r = r.substring(s.raw.length),
                        "_" !== s.raw.slice(-1) && (D = s.raw.slice(-1)),
                        o = !0,
                        (a = i[i.length - 1]) && "text" === a.type ? (a.raw += s.raw,
                        a.text += s.text) : i.push(s),
                        "continue";
                    if (r) {
                        var e = "Infinite loop on byte: " + r.charCodeAt(0);
                        if (c.options.silent)
                            return console.error(e),
                            "break";
                        throw new Error(e)
                    }
                }();
                if ("continue" !== t && "break" === t)
                    break
            }
            return i
        }
        ,
        n = t,
        u = [{
            key: "rules",
            get: function() {
                return {
                    block: B,
                    inline: w
                }
            }
        }],
        (e = null) && i(n.prototype, e),
        u && i(n, u),
        Object.defineProperty(n, "prototype", {
            writable: !1
        }),
        t
    }()
      , _ = function() {
        function e(e) {
            this.options = e || r.defaults
        }
        var u = e.prototype;
        return u.code = function(e, u, t) {
            var n, u = (u || "").match(/\S*/)[0];
            return this.options.highlight && null != (n = this.options.highlight(e, u)) && n !== e && (t = !0,
            e = n),
            e = e.replace(/\n$/, "") + "\n",
            u ? '<pre><code class="' + this.options.langPrefix + d(u) + '">' + (t ? e : d(e, !0)) + "</code></pre>\n" : "<pre><code>" + (t ? e : d(e, !0)) + "</code></pre>\n"
        }
        ,
        u.blockquote = function(e) {
            return "<blockquote>\n" + e + "</blockquote>\n"
        }
        ,
        u.html = function(e, u) {
            return e
        }
        ,
        u.heading = function(e, u, t, n) {
            return this.options.headerIds ? "<h" + u + ' id="' + (this.options.headerPrefix + n.slug(t)) + '">' + e + "</h" + u + ">\n" : "<h" + u + ">" + e + "</h" + u + ">\n"
        }
        ,
        u.hr = function() {
            return this.options.xhtml ? "<hr/>\n" : "<hr>\n"
        }
        ,
        u.list = function(e, u, t) {
            var n = u ? "ol" : "ul";
            return "<" + n + (u && 1 !== t ? ' start="' + t + '"' : "") + ">\n" + e + "</" + n + ">\n"
        }
        ,
        u.listitem = function(e) {
            return "<li>" + e + "</li>\n"
        }
        ,
        u.checkbox = function(e) {
            return "<input " + (e ? 'checked="" ' : "") + 'disabled="" type="checkbox"' + (this.options.xhtml ? " /" : "") + "> "
        }
        ,
        u.paragraph = function(e) {
            return "<p>" + e + "</p>\n"
        }
        ,
        u.table = function(e, u) {
            return "<table>\n<thead>\n" + e + "</thead>\n" + (u = u && "<tbody>" + u + "</tbody>") + "</table>\n"
        }
        ,
        u.tablerow = function(e) {
            return "<tr>\n" + e + "</tr>\n"
        }
        ,
        u.tablecell = function(e, u) {
            var t = u.header ? "th" : "td";
            return (u.align ? "<" + t + ' align="' + u.align + '">' : "<" + t + ">") + e + "</" + t + ">\n"
        }
        ,
        u.strong = function(e) {
            return "<strong>" + e + "</strong>"
        }
        ,
        u.em = function(e) {
            return "<em>" + e + "</em>"
        }
        ,
        u.codespan = function(e) {
            return "<code>" + e + "</code>"
        }
        ,
        u.br = function() {
            return this.options.xhtml ? "<br/>" : "<br>"
        }
        ,
        u.del = function(e) {
            return "<del>" + e + "</del>"
        }
        ,
        u.link = function(e, u, t) {
            return null === (e = f(this.options.sanitize, this.options.baseUrl, e)) ? t : (e = '<a href="' + e + '"',
            u && (e += ' title="' + u + '"'),
            e + ">" + t + "</a>")
        }
        ,
        u.image = function(e, u, t) {
            return null === (e = f(this.options.sanitize, this.options.baseUrl, e)) ? t : (e = '<img src="' + e + '" alt="' + t + '"',
            u && (e += ' title="' + u + '"'),
            e + (this.options.xhtml ? "/>" : ">"))
        }
        ,
        u.text = function(e) {
            return e
        }
        ,
        e
    }()
      , z = function() {
        function e() {}
        var u = e.prototype;
        return u.strong = function(e) {
            return e
        }
        ,
        u.em = function(e) {
            return e
        }
        ,
        u.codespan = function(e) {
            return e
        }
        ,
        u.del = function(e) {
            return e
        }
        ,
        u.html = function(e) {
            return e
        }
        ,
        u.text = function(e) {
            return e
        }
        ,
        u.link = function(e, u, t) {
            return "" + t
        }
        ,
        u.image = function(e, u, t) {
            return "" + t
        }
        ,
        u.br = function() {
            return ""
        }
        ,
        e
    }()
      , $ = function() {
        function e() {
            this.seen = {}
        }
        var u = e.prototype;
        return u.serialize = function(e) {
            return e.toLowerCase().trim().replace(/<[!\/a-z].*?>/gi, "").replace(/[\u2000-\u206F\u2E00-\u2E7F\\'!"#$%&()*+,./:;<=>?@[\]^`{|}~]/g, "").replace(/\s/g, "-")
        }
        ,
        u.getNextSafeSlug = function(e, u) {
            var t = e
              , n = 0;
            if (this.seen.hasOwnProperty(t))
                for (n = this.seen[e]; t = e + "-" + ++n,
                this.seen.hasOwnProperty(t); )
                    ;
            return u || (this.seen[e] = n,
            this.seen[t] = 0),
            t
        }
        ,
        u.slug = function(e, u) {
            void 0 === u && (u = {});
            e = this.serialize(e);
            return this.getNextSafeSlug(e, u.dryrun)
        }
        ,
        e
    }()
      , S = function() {
        function t(e) {
            this.options = e || r.defaults,
            this.options.renderer = this.options.renderer || new _,
            this.renderer = this.options.renderer,
            this.renderer.options = this.options,
            this.textRenderer = new z,
            this.slugger = new $
        }
        t.parse = function(e, u) {
            return new t(u).parse(e)
        }
        ,
        t.parseInline = function(e, u) {
            return new t(u).parseInline(e)
        }
        ;
        var e = t.prototype;
        return e.parse = function(e, u) {
            void 0 === u && (u = !0);
            for (var t, n, r, i, s, a, l, o, D, c, p, h, F, f, g, A, d = "", C = e.length, k = 0; k < C; k++)
                if (o = e[k],
                this.options.extensions && this.options.extensions.renderers && this.options.extensions.renderers[o.type] && (!1 !== (A = this.options.extensions.renderers[o.type].call({
                    parser: this
                }, o)) || !["space", "hr", "heading", "code", "table", "blockquote", "list", "html", "paragraph", "text"].includes(o.type)))
                    d += A || "";
                else
                    switch (o.type) {
                    case "space":
                        continue;
                    case "hr":
                        d += this.renderer.hr();
                        continue;
                    case "heading":
                        d += this.renderer.heading(this.parseInline(o.tokens), o.depth, x(this.parseInline(o.tokens, this.textRenderer)), this.slugger);
                        continue;
                    case "code":
                        d += this.renderer.code(o.text, o.lang, o.escaped);
                        continue;
                    case "table":
                        for (a = D = "",
                        r = o.header.length,
                        t = 0; t < r; t++)
                            a += this.renderer.tablecell(this.parseInline(o.header[t].tokens), {
                                header: !0,
                                align: o.align[t]
                            });
                        for (D += this.renderer.tablerow(a),
                        l = "",
                        r = o.rows.length,
                        t = 0; t < r; t++) {
                            for (a = "",
                            i = (s = o.rows[t]).length,
                            n = 0; n < i; n++)
                                a += this.renderer.tablecell(this.parseInline(s[n].tokens), {
                                    header: !1,
                                    align: o.align[n]
                                });
                            l += this.renderer.tablerow(a)
                        }
                        d += this.renderer.table(D, l);
                        continue;
                    case "blockquote":
                        l = this.parse(o.tokens),
                        d += this.renderer.blockquote(l);
                        continue;
                    case "list":
                        for (D = o.ordered,
                        E = o.start,
                        c = o.loose,
                        r = o.items.length,
                        l = "",
                        t = 0; t < r; t++)
                            F = (h = o.items[t]).checked,
                            f = h.task,
                            p = "",
                            h.task && (g = this.renderer.checkbox(F),
                            c ? 0 < h.tokens.length && "paragraph" === h.tokens[0].type ? (h.tokens[0].text = g + " " + h.tokens[0].text,
                            h.tokens[0].tokens && 0 < h.tokens[0].tokens.length && "text" === h.tokens[0].tokens[0].type && (h.tokens[0].tokens[0].text = g + " " + h.tokens[0].tokens[0].text)) : h.tokens.unshift({
                                type: "text",
                                text: g
                            }) : p += g),
                            p += this.parse(h.tokens, c),
                            l += this.renderer.listitem(p, f, F);
                        d += this.renderer.list(l, D, E);
                        continue;
                    case "html":
                        d += this.renderer.html(o.text, o.block);
                        continue;
                    case "paragraph":
                        d += this.renderer.paragraph(this.parseInline(o.tokens));
                        continue;
                    case "text":
                        for (l = o.tokens ? this.parseInline(o.tokens) : o.text; k + 1 < C && "text" === e[k + 1].type; )
                            l += "\n" + ((o = e[++k]).tokens ? this.parseInline(o.tokens) : o.text);
                        d += u ? this.renderer.paragraph(l) : l;
                        continue;
                    default:
                        var E = 'Token with "' + o.type + '" type was not found.';
                        if (this.options.silent)
                            return void console.error(E);
                        throw new Error(E)
                    }
            return d
        }
        ,
        e.parseInline = function(e, u) {
            u = u || this.renderer;
            for (var t, n, r = "", i = e.length, s = 0; s < i; s++)
                if (t = e[s],
                this.options.extensions && this.options.extensions.renderers && this.options.extensions.renderers[t.type] && (!1 !== (n = this.options.extensions.renderers[t.type].call({
                    parser: this
                }, t)) || !["escape", "html", "link", "image", "strong", "em", "codespan", "br", "del", "text"].includes(t.type)))
                    r += n || "";
                else
                    switch (t.type) {
                    case "escape":
                        r += u.text(t.text);
                        break;
                    case "html":
                        r += u.html(t.text);
                        break;
                    case "link":
                        r += u.link(t.href, t.title, this.parseInline(t.tokens, u));
                        break;
                    case "image":
                        r += u.image(t.href, t.title, t.text);
                        break;
                    case "strong":
                        r += u.strong(this.parseInline(t.tokens, u));
                        break;
                    case "em":
                        r += u.em(this.parseInline(t.tokens, u));
                        break;
                    case "codespan":
                        r += u.codespan(t.text);
                        break;
                    case "br":
                        r += u.br();
                        break;
                    case "del":
                        r += u.del(this.parseInline(t.tokens, u));
                        break;
                    case "text":
                        r += u.text(t.text);
                        break;
                    default:
                        var a = 'Token with "' + t.type + '" type was not found.';
                        if (this.options.silent)
                            return void console.error(a);
                        throw new Error(a)
                    }
            return r
        }
        ,
        t
    }()
      , T = function() {
        function e(e) {
            this.options = e || r.defaults
        }
        var u = e.prototype;
        return u.preprocess = function(e) {
            return e
        }
        ,
        u.postprocess = function(e) {
            return e
        }
        ,
        e
    }();
    function R(f, g) {
        return function(e, t, n) {
            "function" == typeof t && (n = t,
            t = null);
            var r, i, s, u, a = A({}, t), l = (t = A({}, I.defaults, a),
            r = t.silent,
            i = t.async,
            s = n,
            function(e) {
                var u;
                if (e.message += "\nPlease report this to https://github.com/markedjs/marked.",
                r)
                    return u = "<p>An error occurred:</p><pre>" + d(e.message + "", !0) + "</pre>",
                    i ? Promise.resolve(u) : s ? void s(null, u) : u;
                if (i)
                    return Promise.reject(e);
                if (!s)
                    throw e;
                s(e)
            }
            );
            if (null == e)
                return l(new Error("marked(): input parameter is undefined or null"));
            if ("string" != typeof e)
                return l(new Error("marked(): input parameter is of type " + Object.prototype.toString.call(e) + ", string expected"));
            if (a = n,
            (u = t) && !u.silent && (a && console.warn("marked(): callback is deprecated since version 5.0.0, should not be used and will be removed in the future. Read more here: https://marked.js.org/using_pro#async"),
            (u.sanitize || u.sanitizer) && console.warn("marked(): sanitize and sanitizer parameters are deprecated since version 0.7.0, should not be used and will be removed in the future. Read more here: https://marked.js.org/#/USING_ADVANCED.md#options"),
            !u.highlight && "language-" === u.langPrefix || console.warn("marked(): highlight and langPrefix parameters are deprecated since version 5.0.0, should not be used and will be removed in the future. Instead use https://www.npmjs.com/package/marked-highlight."),
            u.mangle && console.warn("marked(): mangle parameter is enabled by default, but is deprecated since version 5.0.0, and will be removed in the future. To clear this warning, install https://www.npmjs.com/package/marked-mangle, or disable by setting `{mangle: false}`."),
            u.baseUrl && console.warn("marked(): baseUrl parameter is deprecated since version 5.0.0, should not be used and will be removed in the future. Instead use https://www.npmjs.com/package/marked-base-url."),
            u.smartypants && console.warn("marked(): smartypants parameter is deprecated since version 5.0.0, should not be used and will be removed in the future. Instead use https://www.npmjs.com/package/marked-smartypants."),
            u.xhtml && console.warn("marked(): xhtml parameter is deprecated since version 5.0.0, should not be used and will be removed in the future. Instead use https://www.npmjs.com/package/marked-xhtml."),
            u.headerIds || u.headerPrefix) && console.warn("marked(): headerIds and headerPrefix parameters enabled by default, but are deprecated since version 5.0.0, and will be removed in the future. To clear this warning, install  https://www.npmjs.com/package/marked-gfm-heading-id, or disable by setting `{headerIds: false}`."),
            t.hooks && (t.hooks.options = t),
            n) {
                var o, D = t.highlight;
                try {
                    t.hooks && (e = t.hooks.preprocess(e)),
                    o = f(e, t)
                } catch (e) {
                    return l(e)
                }
                var c, p = function(u) {
                    var e;
                    if (!u)
                        try {
                            t.walkTokens && I.walkTokens(o, t.walkTokens),
                            e = g(o, t),
                            t.hooks && (e = t.hooks.postprocess(e))
                        } catch (e) {
                            u = e
                        }
                    return t.highlight = D,
                    u ? l(u) : n(null, e)
                };
                return !D || D.length < 3 ? p() : (delete t.highlight,
                o.length ? (c = 0,
                I.walkTokens(o, function(t) {
                    "code" === t.type && (c++,
                    setTimeout(function() {
                        D(t.text, t.lang, function(e, u) {
                            if (e)
                                return p(e);
                            null != u && u !== t.text && (t.text = u,
                            t.escaped = !0),
                            0 === --c && p()
                        })
                    }, 0))
                }),
                void (0 === c && p())) : p())
            }
            if (t.async)
                return Promise.resolve(t.hooks ? t.hooks.preprocess(e) : e).then(function(e) {
                    return f(e, t)
                }).then(function(e) {
                    return t.walkTokens ? Promise.all(I.walkTokens(e, t.walkTokens)).then(function() {
                        return e
                    }) : e
                }).then(function(e) {
                    return g(e, t)
                }).then(function(e) {
                    return t.hooks ? t.hooks.postprocess(e) : e
                }).catch(l);
            try {
                t.hooks && (e = t.hooks.preprocess(e));
                var h = f(e, t)
                  , F = (t.walkTokens && I.walkTokens(h, t.walkTokens),
                g(h, t));
                return F = t.hooks ? t.hooks.postprocess(F) : F
            } catch (e) {
                return l(e)
            }
        }
    }
    function I(e, u, t) {
        return R(y.lex, S.parse)(e, u, t)
    }
    T.passThroughHooks = new Set(["preprocess", "postprocess"]),
    I.options = I.setOptions = function(e) {
        return I.defaults = A({}, I.defaults, e),
        e = I.defaults,
        r.defaults = e,
        I
    }
    ,
    I.getDefaults = e,
    I.defaults = r.defaults,
    I.use = function() {
        for (var D = I.defaults.extensions || {
            renderers: {},
            childTokens: {}
        }, e = arguments.length, u = new Array(e), t = 0; t < e; t++)
            u[t] = arguments[t];
        u.forEach(function(s) {
            var t, e = A({}, s);
            if (e.async = I.defaults.async || e.async || !1,
            s.extensions && (s.extensions.forEach(function(r) {
                if (!r.name)
                    throw new Error("extension name required");
                var i;
                if (r.renderer && (i = D.renderers[r.name],
                D.renderers[r.name] = i ? function() {
                    for (var e = arguments.length, u = new Array(e), t = 0; t < e; t++)
                        u[t] = arguments[t];
                    var n = r.renderer.apply(this, u);
                    return n = !1 === n ? i.apply(this, u) : n
                }
                : r.renderer),
                r.tokenizer) {
                    if (!r.level || "block" !== r.level && "inline" !== r.level)
                        throw new Error("extension level must be 'block' or 'inline'");
                    D[r.level] ? D[r.level].unshift(r.tokenizer) : D[r.level] = [r.tokenizer],
                    r.start && ("block" === r.level ? D.startBlock ? D.startBlock.push(r.start) : D.startBlock = [r.start] : "inline" === r.level && (D.startInline ? D.startInline.push(r.start) : D.startInline = [r.start]))
                }
                r.childTokens && (D.childTokens[r.name] = r.childTokens)
            }),
            e.extensions = D),
            s.renderer) {
                var u, a = I.defaults.renderer || new _;
                for (u in s.renderer)
                    !function(r) {
                        var i = a[r];
                        a[r] = function() {
                            for (var e = arguments.length, u = new Array(e), t = 0; t < e; t++)
                                u[t] = arguments[t];
                            var n = s.renderer[r].apply(a, u);
                            return n = !1 === n ? i.apply(a, u) : n
                        }
                    }(u);
                e.renderer = a
            }
            if (s.tokenizer) {
                var n, l = I.defaults.tokenizer || new b;
                for (n in s.tokenizer)
                    !function(r) {
                        var i = l[r];
                        l[r] = function() {
                            for (var e = arguments.length, u = new Array(e), t = 0; t < e; t++)
                                u[t] = arguments[t];
                            var n = s.tokenizer[r].apply(l, u);
                            return n = !1 === n ? i.apply(l, u) : n
                        }
                    }(n);
                e.tokenizer = l
            }
            if (s.hooks) {
                var r, o = I.defaults.hooks || new T;
                for (r in s.hooks)
                    !function(r) {
                        var i = o[r];
                        T.passThroughHooks.has(r) ? o[r] = function(e) {
                            return I.defaults.async ? Promise.resolve(s.hooks[r].call(o, e)).then(function(e) {
                                return i.call(o, e)
                            }) : (e = s.hooks[r].call(o, e),
                            i.call(o, e))
                        }
                        : o[r] = function() {
                            for (var e = arguments.length, u = new Array(e), t = 0; t < e; t++)
                                u[t] = arguments[t];
                            var n = s.hooks[r].apply(o, u);
                            return n = !1 === n ? i.apply(o, u) : n
                        }
                    }(r);
                e.hooks = o
            }
            s.walkTokens && (t = I.defaults.walkTokens,
            e.walkTokens = function(e) {
                var u = [];
                return u.push(s.walkTokens.call(this, e)),
                u = t ? u.concat(t.call(this, e)) : u
            }
            ),
            I.setOptions(e)
        })
    }
    ,
    I.walkTokens = function(e, a) {
        for (var l, o = [], u = D(e); !(l = u()).done; )
            !function() {
                var u = l.value;
                switch (o = o.concat(a.call(I, u)),
                u.type) {
                case "table":
                    for (var e = D(u.header); !(t = e()).done; ) {
                        var t = t.value;
                        o = o.concat(I.walkTokens(t.tokens, a))
                    }
                    for (var n, r = D(u.rows); !(n = r()).done; )
                        for (var i = D(n.value); !(s = i()).done; ) {
                            var s = s.value;
                            o = o.concat(I.walkTokens(s.tokens, a))
                        }
                    break;
                case "list":
                    o = o.concat(I.walkTokens(u.items, a));
                    break;
                default:
                    I.defaults.extensions && I.defaults.extensions.childTokens && I.defaults.extensions.childTokens[u.type] ? I.defaults.extensions.childTokens[u.type].forEach(function(e) {
                        o = o.concat(I.walkTokens(u[e], a))
                    }) : u.tokens && (o = o.concat(I.walkTokens(u.tokens, a)))
                }
            }();
        return o
    }
    ,
    I.parseInline = R(y.lexInline, S.parseInline),
    I.Parser = S,
    I.parser = S.parse,
    I.Renderer = _,
    I.TextRenderer = z,
    I.Lexer = y,
    I.lexer = y.lex,
    I.Tokenizer = b,
    I.Slugger = $,
    I.Hooks = T;
    var C = (I.parse = I).options
      , L = I.setOptions
      , U = I.use
      , Q = I.walkTokens
      , M = I.parseInline
      , N = I
      , H = S.parse
      , X = y.lex;
    r.Hooks = T,
    r.Lexer = y,
    r.Parser = S,
    r.Renderer = _,
    r.Slugger = $,
    r.TextRenderer = z,
    r.Tokenizer = b,
    r.getDefaults = e,
    r.lexer = X,
    r.marked = I,
    r.options = C,
    r.parse = N,
    r.parseInline = M,
    r.parser = H,
    r.setOptions = L,
    r.use = U,
    r.walkTokens = Q
});
