var bu=Object.defineProperty;var is=J=>{throw TypeError(J)};var yu=(J,Z,ve)=>Z in J?bu(J,Z,{enumerable:!0,configurable:!0,writable:!0,value:ve}):J[Z]=ve;var S=(J,Z,ve)=>yu(J,typeof Z!="symbol"?Z+"":Z,ve),Za=(J,Z,ve)=>Z.has(J)||is("Cannot "+ve);var h=(J,Z,ve)=>(Za(J,Z,"read from private field"),ve?ve.call(J):Z.get(J)),I=(J,Z,ve)=>Z.has(J)?is("Cannot add the same private member more than once"):Z instanceof WeakSet?Z.add(J):Z.set(J,ve),R=(J,Z,ve,Wt)=>(Za(J,Z,"write to private field"),Wt?Wt.call(J,ve):Z.set(J,ve),ve),U=(J,Z,ve)=>(Za(J,Z,"access private method"),ve);var os={},ss;function _u(){var Fo,Tn,Kt,ln,$n,Rn,Pn,Ot,In,Ue,ar,Lt,lt,St,On,cn,q,Qa,pr,ei,ls,cs,Dn,wu,gr,Vo,jo,Ke,ir,Ze,un,Ie,Ye,Oe,Ge,Ct,fn,Yt,Ln,or,sr,Mt,Vr,W,us,fs,ti,hs,ni,mr,Gr,ri,ai,ct,Nt,ut,hn,lr,jr,Fa,Dt,Qe;if(ss)return os;ss=1;const J=!1;var Z=Array.isArray,ve=Array.prototype.indexOf,Wt=Array.prototype.includes,ds=Array.from,br=Object.keys,pn=Object.defineProperty,gn=Object.getOwnPropertyDescriptor,vs=Object.getOwnPropertyDescriptors,ps=Object.prototype,gs=Array.prototype,ii=Object.getPrototypeOf,oi=Object.isExtensible;const Ht=()=>{};function ms(e){for(var t=0;t<e.length;t++)e[t]()}function si(){var e,t,n=new Promise((r,a)=>{e=r,t=a});return{promise:n,resolve:e,reject:t}}const we=2,mn=4,yr=8,qr=1<<24,nt=16,rt=32,vt=64,Wr=128,Xr=256,qe=512,pe=1024,fe=2048,at=4096,We=8192,je=16384,Xt=32768,wr=1<<25,Ft=65536,_r=1<<17,bs=1<<18,Jt=1<<19,ys=1<<20,Zt=65536,kr=1<<21,bn=1<<22,Vt=1<<23,yn=Symbol("$state"),li=Symbol("component"),ws=Symbol("legacy props"),_s=Symbol(""),xr=Symbol("attributes"),Jr=Symbol("class"),Zr=Symbol("style"),Un=Symbol("text"),Hn=Symbol("form reset"),Fn=new class extends Error{constructor(){super(...arguments);S(this,"name","StaleReactionError");S(this,"message","The reaction that called `getAbortSignal()` was re-run or destroyed")}},Vn=!!((Fo=globalThis.document)!=null&&Fo.contentType)&&globalThis.document.contentType.includes("xml"),jn=3,Bn=8;function ci(e){return e===this.v}function ui(e,t){return e!=e?t==t:e!==t||e!==null&&typeof e=="object"||typeof e=="function"}function ks(e){return!ui(e,this.v)}function xs(e){throw new Error("https://svelte.dev/e/lifecycle_outside_component")}function Es(){throw new Error("https://svelte.dev/e/async_derived_orphan")}function Ss(e){throw new Error("https://svelte.dev/e/effect_in_teardown")}function Cs(){throw new Error("https://svelte.dev/e/effect_in_unowned_derived")}function As(e){throw new Error("https://svelte.dev/e/effect_orphan")}function Ts(){throw new Error("https://svelte.dev/e/effect_update_depth_exceeded")}function $s(){throw new Error("https://svelte.dev/e/hydration_failed")}function Rs(){throw new Error("https://svelte.dev/e/state_descriptors_fixed")}function Ps(){throw new Error("https://svelte.dev/e/state_prototype_fixed")}function Is(){throw new Error("https://svelte.dev/e/state_unsafe_mutation")}function Os(){throw new Error("https://svelte.dev/e/svelte_boundary_reset_onerror")}let Ls=!1;const Ms=1,Ns=2,Qr="[",fi="[!",hi="[?",di="]",Qt={},he=Symbol("uninitialized"),vi="http://www.w3.org/1999/xhtml",Ds="http://www.w3.org/2000/svg",Us="http://www.w3.org/1998/Math/MathML",Hs="@attach";let Ce=null;function wn(e){Ce=e}function $t(e,t=!1,n){Ce={p:Ce,i:!1,c:null,e:null,s:e,x:null,r:L,l:null}}function Rt(e){var t=Ce,n=t.e;if(n!==null){t.e=null;for(var r of n)Ji(r)}return e!==void 0&&(t.x=e),t.i=!0,Ce=t.p,ea(e)}function ea(e={}){return pn(e,li,{value:!0}),e}function pi(){return!0}let en=[];function gi(){var e=en;en=[],ms(e)}function pt(e){if(en.length===0&&!qn){var t=en;queueMicrotask(()=>{t===en&&gi()})}en.push(e)}function Fs(){for(;en.length>0;)gi()}function Vs(){console.warn("https://svelte.dev/e/derived_inert")}function zn(e){console.warn("https://svelte.dev/e/hydration_mismatch")}function js(){console.warn("https://svelte.dev/e/select_multiple_invalid_value")}function Bs(){console.warn("https://svelte.dev/e/svelte_boundary_reset_noop")}let O=!1;function gt(e){O=e}let N;function Re(e){if(e===null)throw zn(),Qt;return N=e}function tn(){return Re(bt(N))}function de(e){if(O){if(bt(N)!==null)throw zn(),Qt;N=e}}function ta(e=1){if(O){for(var t=e,n=N;t--;)n=bt(n);N=n}}function na(e=!0){for(var t=0,n=N;;){if(n.nodeType===Bn){var r=n.data;if(r===di){if(t===0)return n;t-=1}else(r===Qr||r===fi||r[0]==="["&&!isNaN(Number(r.slice(1))))&&(t+=1)}var a=bt(n);e&&n.remove(),n=a}}function mi(e){if(!e||e.nodeType!==Bn)throw zn(),Qt;return e.data}function Pt(e){if(typeof e!="object"||e===null||yn in e||li in e)return e;const t=ii(e);if(t!==ps&&t!==gs)return e;var n=new Map,r=Z(e),a=H(0),o=an,s=l=>{if(an===o)return l();var f=F,u=an;Xe(null),zi(o);var d=l();return Xe(f),zi(u),d};return r&&n.set("length",H(e.length)),new Proxy(e,{defineProperty(l,f,u){(!("value"in u)||u.configurable===!1||u.enumerable===!1||u.writable===!1)&&Rs();var d=n.get(f);return d===void 0?s(()=>{var m=H(u.value);return n.set(f,m),m}):E(d,u.value,!0),!0},deleteProperty(l,f){var u=n.get(f);if(u===void 0){if(f in l){const d=s(()=>H(he));n.set(f,d),Xn(a)}}else E(u,he),Xn(a);return!0},get(l,f,u){var p;if(f===yn)return e;var d=n.get(f),m=f in l;if(d===void 0&&(!m||(p=gn(l,f))!=null&&p.writable)&&(d=s(()=>{var b=Pt(m?l[f]:he),y=H(b);return y}),n.set(f,d)),d!==void 0){var g=i(d);return g===he?void 0:g}return Reflect.get(l,f,u)},getOwnPropertyDescriptor(l,f){var u=Reflect.getOwnPropertyDescriptor(l,f);if(u&&"value"in u){var d=n.get(f);d&&(u.value=i(d))}else if(u===void 0){var m=n.get(f),g=m==null?void 0:m.v;if(m!==void 0&&g!==he)return{enumerable:!0,configurable:!0,value:g,writable:!0}}return u},has(l,f){var g;if(f===yn)return!0;var u=n.get(f),d=u!==void 0&&u.v!==he||Reflect.has(l,f);if(u!==void 0||L!==null&&(!d||(g=gn(l,f))!=null&&g.writable)){u===void 0&&(u=s(()=>{var p=d?Pt(l[f]):he,b=H(p);return b}),n.set(f,u));var m=i(u);if(m===he)return!1}return d},set(l,f,u,d){var se;var m=n.get(f),g=f in l;if(r&&f==="length")for(var p=u;p<m.v;p+=1){var b=n.get(p+"");b!==void 0?E(b,he):p in l&&(b=s(()=>H(he)),n.set(p+"",b))}if(m===void 0)(!g||(se=gn(l,f))!=null&&se.writable)&&(m=s(()=>H(void 0)),E(m,Pt(u)),n.set(f,m));else{g=m.v!==he;var y=s(()=>Pt(u));E(m,y)}var M=Reflect.getOwnPropertyDescriptor(l,f);if(M!=null&&M.set&&M.set.call(d,u),!g){if(r&&typeof f=="string"){var P=n.get("length"),z=Number(f);Number.isInteger(z)&&z>=P.v&&E(P,z+1)}Xn(a)}return!0},ownKeys(l){i(a);var f=Reflect.ownKeys(l).filter(m=>{var g=n.get(m);return g===void 0||g.v!==he});for(var[u,d]of n)d.v!==he&&!(u in l)&&f.push(u);return f},setPrototypeOf(){Ps()}})}function bi(e){try{if(e!==null&&typeof e=="object"&&yn in e)return e[yn]}catch{}return e}function yi(e,t){return Object.is(bi(e),bi(t))}var nn,ra,wi,_i,ki;function aa(){if(nn===void 0){nn=window,ra=document,wi=/Firefox/.test(navigator.userAgent);var e=Element.prototype,t=Node.prototype,n=Text.prototype;_i=gn(t,"firstChild").get,ki=gn(t,"nextSibling").get,oi(e)&&(e[Jr]=void 0,e[xr]=null,e[Zr]=void 0,e.__e=void 0),oi(n)&&(n[Un]=void 0)}}function mt(e=""){return document.createTextNode(e)}function Me(e){return _i.call(e)}function bt(e){return ki.call(e)}function _e(e,t){if(!O)return Me(e);var n=Me(N);if(n===null)n=N.appendChild(mt());else if(t&&n.nodeType!==jn){var r=mt();return n==null||n.before(r),Re(r),r}return t&&Er(n),Re(n),n}function _n(e,t=!1){if(!O){var n=Me(e);return n instanceof Comment&&n.data===""?bt(n):n}if(t){if((N==null?void 0:N.nodeType)!==jn){var r=mt();return N==null||N.before(r),Re(r),r}Er(N)}return N}function kn(e,t=!1){if(!O)return Me(e);var n=_e(e,t);return de(e),n}function re(e,t=1,n=!1){let r=O?N:e;for(var a;t--;)a=r,r=bt(r);if(!O)return r;if(n){if((r==null?void 0:r.nodeType)!==jn){var o=mt();return r===null?a==null||a.after(o):r.before(o),Re(o),o}Er(r)}return Re(r),r}function zs(e){e.textContent=""}function ia(e,t,n){return t==null||t===vi?document.createElement(e):document.createElementNS(t,e)}function Er(e){if(e.nodeValue.length<65536)return;let t=e.nextSibling;for(;t!==null&&t.nodeType===jn;)t.remove(),e.nodeValue+=t.nodeValue,t=e.nextSibling}function Ks(e){var t=L;if(t===null)return F.f|=Vt,e;if((t.f&Xt)===0&&(t.f&mn)===0)throw e;yt(e,t)}function yt(e,t){if(!(t!==null&&(t.f&je)!==0)){for(;t!==null;){if((t.f&Wr)!==0&&(t.f&(je|wr))===0){if((t.f&Xt)===0)throw e;try{t.b.error(e);return}catch(n){e=n}}t=t.parent}throw e}}const Ys=-7169;function ce(e,t){e.f=e.f&Ys|t}function oa(e){(e.f&qe)!==0||e.deps===null?ce(e,pe):ce(e,at)}function xi(e){if(e!==null)for(const t of e)(t.f&we)===0||(t.f&Zt)===0||(t.f^=Zt,xi(t.deps))}function Ei(e,t,n){(e.f&fe)!==0?t.add(e):(e.f&at)!==0&&n.add(e),xi(e.deps),ce(e,pe)}function Si(e,t,n){if(e==null)return t(void 0),Ht;const r=Qn(()=>e.subscribe(t,n));return r.unsubscribe?()=>r.unsubscribe():r}const xn=[];function Gs(e,t=Ht){let n=null;const r=new Set;function a(l){if(ui(e,l)&&(e=l,n)){const f=!xn.length;for(const u of r)u[1](),xn.push(u,e);if(f){for(let u=0;u<xn.length;u+=2)xn[u][0](xn[u+1]);xn.length=0}}}function o(l){a(l(e))}function s(l,f=Ht){const u=[l,f];return r.add(u),r.size===1&&(n=t(a,o)||Ht),l(e),()=>{r.delete(u),r.size===0&&n&&(n(),n=null)}}return{set:a,update:o,subscribe:s}}function Kn(e){let t;return Si(e,n=>t=n)(),t}let sa=Symbol("unmounted");function Ci(e,t,n){const r=n[t]??(n[t]={store:null,source:Hi(void 0),unsubscribe:Ht});if(r.store!==e&&!(sa in n))if(r.unsubscribe(),r.store=e??null,e==null)r.source.v=void 0,r.unsubscribe=Ht;else{var a=!0;r.unsubscribe=Si(e,o=>{a?r.source.v=o:E(r.source,o)}),a=!1}return e&&sa in n?Kn(e):i(r.source)}function qs(){const e={};function t(){Rr(()=>{for(var n in e)e[n].unsubscribe();pn(e,sa,{enumerable:!1,value:!0})})}return[e,t]}function Ws(e,t){if(t){const n=document.body;e.autofocus=!0,pt(()=>{document.activeElement===n&&e.focus()})}}let Ai=!1;function Ti(){Ai||(Ai=!0,document.addEventListener("reset",e=>{Promise.resolve().then(()=>{var t;if(!e.defaultPrevented)for(const n of e.target.elements)(t=n[Hn])==null||t.call(n)})},{capture:!0}))}function En(e){var t=F,n=L;Xe(null),_t(null);try{return e()}finally{Xe(t),_t(n)}}function Xs(e,t,n,r=n){e.addEventListener(t,()=>En(n));const a=e[Hn];a?e[Hn]=()=>{a(),r(!0)}:e[Hn]=()=>r(!0),Ti()}function $i(e,t,n,r){const a=la;var o=e.filter(p=>!p.settled),s=t.map(a);if(n.length===0&&o.length===0){r(s);return}var l=L,f=Js(),u=o.length===1?o[0].promise:o.length>1?Promise.all(o.map(p=>p.promise)):null;function d(p){if((l.f&je)===0){f();try{r([...s,...p])}catch(b){yt(b,l)}Sr()}}var m=Ri();if(n.length===0){u.then(()=>d([])).finally(m);return}function g(){Promise.all(n.map(p=>Zs(p))).then(d).catch(p=>yt(p,l)).finally(m)}u?u.then(()=>{f(),g(),Sr()}):g()}function Js(){var e=L,t=F,n=Ce,r=T;return function(o=!0){_t(e),Xe(t),wn(n),o&&(e.f&je)===0&&(r==null||r.activate(),r==null||r.apply())}}function Sr(e=!0){_t(null),Xe(null),wn(null),e&&(T==null||T.deactivate())}function Ri(){var e=L,t=e.b,n=T,r=!!(t!=null&&t.is_rendered());return t==null||t.update_pending_count(1,n),n.increment(r,e),()=>{t==null||t.update_pending_count(-1,n),n.decrement(r,e)}}function la(e){var t=we|fe;return L!==null&&(L.f|=Jt),{ctx:Ce,deps:null,effects:null,equals:ci,f:t,fn:e,reactions:null,rv:0,v:he,wv:0,parent:L,ac:null}}const Yn=Symbol("obsolete");function Zs(e,t,n){let r=L;r===null&&Es();var a=void 0,o=Wn(he),s=!F,l=new Set;return ul(()=>{var p,b;var f=L,u=si();a=u.promise;try{Promise.resolve(e()).then(u.resolve,y=>{y!==Fn&&u.reject(y)}).finally(Sr)}catch(y){u.reject(y),Sr()}var d=T;if(s){if((f.f&Xt)!==0)var m=Ri();if((p=r.b)!=null&&p.is_rendered())(b=d.async_deriveds.get(f))==null||b.reject(Yn);else for(const y of l.values())y.reject(Yn);l.add(u),d.async_deriveds.set(f,u)}const g=(y,M=void 0)=>{m==null||m(),l.delete(u),M!==Yn&&(d.activate(),M?(o.f|=Vt,Tr(o,M)):((o.f&Vt)!==0&&(o.f^=Vt),Tr(o,y)),d.deactivate())};u.promise.then(g,y=>g(null,y||"unknown"))}),Rr(()=>{for(const f of l)f.reject(Yn)}),new Promise(f=>{function u(d){function m(){d===a?f(o):u(a)}d.then(m,m)}u(a)})}function Pe(e){const t=la(e);return ji(t),t}function Qs(e){var t=e.effects;if(t!==null){e.effects=null;for(var n=0;n<t.length;n+=1)Ee(t[n])}}function ca(e){var t,n=L,r=e.parent;if(!It&&r!==null&&e.v!==he&&(r.f&(je|We))!==0)return Vs(),e.v;_t(r);try{e.f&=~Zt,Qs(e),t=Gi(e)}finally{_t(n)}return t}function Pi(e){var t=ca(e);if(!e.equals(t)&&(e.wv=Ki(),(!(T!=null&&T.is_fork)||e.deps===null)&&(T!==null?(T.capture(e,t,!0),Gn==null||Gn.capture(e,t,!0)):e.v=t,e.deps===null))){ce(e,pe);return}It||(xe!==null?(pa()||T!=null&&T.is_fork)&&xe.set(e,t):oa(e))}function el(e){var t;if(e.effects!==null)for(const n of e.effects)(n.teardown||n.ac)&&((t=n.teardown)==null||t.call(n),n.ac!==null&&En(()=>{n.ac.abort(Fn),n.ac=null}),n.fn!==null&&(n.teardown=Ht),Zn(n,0),ma(n))}function Ii(e){if(e.effects!==null)for(const t of e.effects)t.teardown&&t.fn!==null&&An(t)}let ua=null,Sn=null,T=null,Gn=null,xe=null,fa=null,qn=!1,ha=!1,Cn=null,Cr=null;var Oi=0;let tl=1;const Fr=class Fr{constructor(){I(this,q);S(this,"id",tl++);I(this,Tn,!1);S(this,"linked",!0);I(this,Kt,null);I(this,ln,null);S(this,"async_deriveds",new Map);S(this,"current",new Map);S(this,"previous",new Map);I(this,$n,new Set);I(this,Rn,new Set);I(this,Pn,0);I(this,Ot,new Map);I(this,In,null);I(this,Ue,[]);I(this,ar,[]);I(this,Lt,new Set);I(this,lt,new Set);I(this,St,new Map);I(this,On,new Set);S(this,"is_fork",!1);I(this,cn,!1);Sn===null?ua=Sn=this:(R(Sn,ln,this),R(this,Kt,Sn)),Sn=this}skip_effect(t){h(this,St).has(t)||h(this,St).set(t,{d:[],m:[]}),h(this,On).delete(t)}unskip_effect(t,n=r=>this.schedule(r)){var r=h(this,St).get(t);if(r){h(this,St).delete(t);for(var a of r.d)ce(a,fe),n(a);for(a of r.m)ce(a,at),n(a)}h(this,On).add(t)}capture(t,n,r=!1){t.v!==he&&!this.previous.has(t)&&this.previous.set(t,t.v),(t.f&Vt)===0&&(this.current.set(t,[n,r]),xe==null||xe.set(t,n)),this.is_fork||(t.v=n)}activate(){T=this}deactivate(){T=null,xe=null}flush(){try{ha=!0,T=this,U(this,q,pr).call(this)}finally{Oi=0,fa=null,Cn=null,Cr=null,ha=!1,T=null,xe=null,wt.clear()}}discard(){var t;for(const n of h(this,Rn))n(this);h(this,Rn).clear();for(const n of this.async_deriveds.values())n.reject(Yn);U(this,q,gr).call(this),(t=h(this,In))==null||t.resolve()}register_created_effect(t){h(this,ar).push(t)}increment(t,n){if(R(this,Pn,h(this,Pn)+1),t){let r=h(this,Ot).get(n)??0;h(this,Ot).set(n,r+1)}}decrement(t,n){if(R(this,Pn,h(this,Pn)-1),t){let r=h(this,Ot).get(n)??0;r===1?h(this,Ot).delete(n):h(this,Ot).set(n,r-1)}h(this,cn)||(R(this,cn,!0),pt(()=>{R(this,cn,!1),this.linked&&this.flush()}))}transfer_effects(t,n){for(const r of t)h(this,Lt).add(r);for(const r of n)h(this,lt).add(r);t.clear(),n.clear()}oncommit(t){h(this,$n).add(t)}ondiscard(t){h(this,Rn).add(t)}settled(){return(h(this,In)??R(this,In,si())).promise}static ensure(){if(T===null){const t=T=new Fr;!ha&&!qn&&pt(()=>{h(t,Tn)||t.flush()})}return T}apply(){{xe=null;return}}schedule(t){var a;if(fa=t,(a=t.b)!=null&&a.is_pending&&(t.f&(mn|yr|qr))!==0&&(t.f&Xt)===0){t.b.defer_effect(t);return}for(var n=t;n.parent!==null;){n=n.parent;var r=n.f;if(Cn!==null&&n===L&&(F===null||(F.f&we)===0))return;if((r&(vt|rt))!==0){if((r&pe)===0)return;n.f^=pe}}h(this,Ue).push(n)}};Tn=new WeakMap,Kt=new WeakMap,ln=new WeakMap,$n=new WeakMap,Rn=new WeakMap,Pn=new WeakMap,Ot=new WeakMap,In=new WeakMap,Ue=new WeakMap,ar=new WeakMap,Lt=new WeakMap,lt=new WeakMap,St=new WeakMap,On=new WeakMap,cn=new WeakMap,q=new WeakSet,Qa=function(){if(this.is_fork)return!0;for(const r of h(this,Ot).keys()){for(var t=r,n=!1;t.parent!==null;){if(h(this,St).has(t)){n=!0;break}t=t.parent}if(!n)return!0}return!1},pr=function(){var f,u,d,m;R(this,Tn,!0),Oi++>1e3&&(U(this,q,gr).call(this),nl());for(const g of h(this,Lt))h(this,lt).delete(g),ce(g,fe),this.schedule(g);for(const g of h(this,lt))ce(g,at),this.schedule(g);const t=h(this,Ue);R(this,Ue,[]),this.apply();var n=Cn=[],r=[],a=Cr=[];for(const g of t)try{U(this,q,ei).call(this,g,n,r)}catch(p){throw Di(g),U(this,q,Qa).call(this)||this.discard(),p}if(T=null,a.length>0){var o=Fr.ensure();for(const g of a)o.schedule(g)}if(Cn=null,Cr=null,U(this,q,Qa).call(this)){U(this,q,Dn).call(this,r),U(this,q,Dn).call(this,n);for(const[g,p]of h(this,St))Ni(g,p);a.length>0&&U(f=T,q,pr).call(f);return}const s=U(this,q,ls).call(this);if(s){U(this,q,Dn).call(this,r),U(this,q,Dn).call(this,n),U(u=s,q,cs).call(u,this);return}h(this,Lt).clear(),h(this,lt).clear();for(const g of h(this,$n))g(this);h(this,$n).clear(),Gn=this,Li(r),Li(n),Gn=null,(d=h(this,In))==null||d.resolve();var l=T;if(h(this,Pn)===0&&(h(this,Ue).length===0||l!==null)&&U(this,q,gr).call(this),h(this,Ue).length>0)if(l!==null){const g=l;h(g,Ue).push(...h(this,Ue).filter(p=>!h(g,Ue).includes(p)))}else l=this;l!==null&&(wt.clear(),U(m=l,q,pr).call(m))},ei=function(t,n,r){t.f^=pe;for(var a=t.first;a!==null;){var o=a.f,s=(o&(rt|vt))!==0,l=s&&(o&pe)!==0,f=l||(o&We)!==0||h(this,St).has(a);if(!f&&a.fn!==null){s?a.f^=pe:(o&mn)!==0?n.push(a):Jn(a)&&((o&nt)!==0&&h(this,lt).add(a),An(a));var u=a.first;if(u!==null){a=u;continue}}for(;a!==null;){var d=a.next;if(d!==null){a=d;break}a=a.parent}}},ls=function(){for(var t=h(this,Kt);t!==null;){if(!t.is_fork){for(const[n,[,r]]of this.current)if(t.current.has(n)&&!r)return t}t=h(t,Kt)}return null},cs=function(t){var r;for(const[a,o]of t.current)!this.previous.has(a)&&t.previous.has(a)&&this.previous.set(a,t.previous.get(a)),this.current.set(a,o);for(const[a,o]of t.async_deriveds){const s=this.async_deriveds.get(a);s&&o.promise.then(s.resolve).catch(s.reject)}t.async_deriveds.clear(),this.transfer_effects(h(t,Lt),h(t,lt));const n=a=>{var o=a.reactions;if(o!==null&&!((a.f&we)!==0&&(a.f&(fe|at))===0))for(const f of o){var s=f.f;if((s&we)!==0)n(f);else{var l=f;s&(bn|nt)&&!this.async_deriveds.has(l)&&(h(this,lt).delete(l),ce(l,fe),this.schedule(l))}}};for(const a of this.current.keys())n(a);this.oncommit(()=>t.discard()),U(r=t,q,gr).call(r),T=this,U(this,q,pr).call(this)},Dn=function(t){for(var n=0;n<t.length;n+=1)Ei(t[n],h(this,Lt),h(this,lt))},wu=function(){var m;for(let g=ua;g!==null;g=h(g,ln)){var t=g.id<this.id,n=[];for(const[p,[b,y]]of this.current){if(g.current.has(p)){var r=g.current.get(p)[0];if(t&&b!==r)g.current.set(p,[b,y]);else continue}n.push(p)}if(t)for(const[p,b]of this.async_deriveds){const y=g.async_deriveds.get(p);y&&b.promise.then(y.resolve).catch(y.reject)}var a=[...g.current.keys()].filter(p=>!g.current.get(p)[1]);if(!(!h(g,Tn)||a.length===0)){var o=a.filter(p=>!this.current.has(p));if(o.length===0)t&&g.discard();else if(n.length>0){if(t)for(const p of h(this,On))g.unskip_effect(p,b=>{var y;(b.f&(nt|bn))!==0?g.schedule(b):U(y=g,q,Dn).call(y,[b])});g.activate();var s=new Set,l=new Map;for(var f of n)Mi(f,o,s,l);l=new Map;var u=[...g.current].filter(([p,b])=>{const y=this.current.get(p);return y?y[0]!==b[0]||y[1]!==b[1]:!0}).map(([p])=>p);if(u.length>0)for(const p of h(this,ar))(p.f&(je|We|_r))===0&&da(p,u,l)&&((p.f&(bn|nt))!==0?(ce(p,fe),g.schedule(p)):h(g,Lt).add(p));if(h(g,Ue).length>0&&!h(g,cn)){g.apply();for(var d of h(g,Ue))U(m=g,q,ei).call(m,d,[],[]);R(g,Ue,[])}g.deactivate()}}}},gr=function(){if(this.linked){var t=h(this,Kt),n=h(this,ln);t===null?ua=n:R(t,ln,n),n===null?Sn=t:R(n,Kt,t),this.linked=!1}};let jt=Fr;function Q(e){var t=qn;qn=!0;try{for(var n;;){if(Fs(),T===null)return n;T.flush()}}finally{qn=t}}function nl(){try{Ts()}catch(e){yt(e,fa)}}let it=null;function Li(e){var t=e.length;if(t!==0){for(var n=0;n<t;){var r=e[n++];if((r.f&(je|We))===0&&Jn(r)&&(it=new Set,An(r),r.deps===null&&r.first===null&&r.nodes===null&&r.teardown===null&&r.ac===null&&to(r),(it==null?void 0:it.size)>0)){wt.clear();for(const a of it){if((a.f&(je|We))!==0)continue;const o=[a];let s=a.parent;for(;s!==null;)it.has(s)&&(it.delete(s),o.push(s)),s=s.parent;for(let l=o.length-1;l>=0;l--){const f=o[l];(f.f&(je|We))===0&&An(f)}}it.clear()}}it=null}}function Mi(e,t,n,r){if(!n.has(e)&&(n.add(e),e.reactions!==null))for(const a of e.reactions){const o=a.f;(o&we)!==0?Mi(a,t,n,r):(o&(bn|nt))!==0&&(o&fe)===0&&da(a,t,r)&&(ce(a,fe),va(a))}}function da(e,t,n){const r=n.get(e);if(r!==void 0)return r;if(e.deps!==null)for(const a of e.deps){if(Wt.call(t,a))return!0;if((a.f&we)!==0&&da(a,t,n))return n.set(a,!0),!0}return n.set(e,!1),!1}function va(e){T.schedule(e)}function Ni(e,t){if(!((e.f&rt)!==0&&(e.f&pe)!==0)){(e.f&fe)!==0?t.d.push(e):(e.f&at)!==0&&t.m.push(e),ce(e,pe);for(var n=e.first;n!==null;)Ni(n,t),n=n.next}}function Di(e){ce(e,pe);for(var t=e.first;t!==null;)Di(t),t=t.next}let Ar=new Set;const wt=new Map;let Ui=!1;function Wn(e,t){var n={f:0,v:e,reactions:null,equals:ci,rv:0,wv:0};return n}function H(e,t){const n=Wn(e);return ji(n),n}function Hi(e,t=!1,n=!0){const r=Wn(e);return t||(r.equals=ks),r}function E(e,t,n=!1){F!==null&&(!ot||(F.f&_r)!==0)&&pi()&&(F.f&(we|nt|bn|_r))!==0&&(kt===null||!kt.has(e))&&Is();let r=n?Pt(t):t;return Tr(e,r,Cr)}function Tr(e,t,n=null){if(!e.equals(t)){It?wt.set(e,t):wt.has(e)||wt.set(e,e.v);var r=jt.ensure();if(r.capture(e,t),(e.f&we)!==0){const a=e;(e.f&fe)!==0&&ca(a),xe===null&&oa(a)}e.wv=Ki(),Fi(e,fe,n),L!==null&&(L.f&pe)!==0&&(L.f&(rt|vt))===0&&(Je===null?al([e]):Je.push(e)),!r.is_fork&&Ar.size>0&&!Ui&&rl()}return t}function rl(){Ui=!1;for(const e of Ar){(e.f&pe)!==0&&ce(e,at);let t;try{t=Jn(e)}catch{t=!0}t&&An(e)}Ar.clear()}function Xn(e){E(e,e.v+1)}function Fi(e,t,n){var r=e.reactions;if(r!==null)for(var a=r.length,o=0;o<a;o++){var s=r[o],l=s.f,f=(l&fe)===0;if(f&&ce(s,t),(l&_r)!==0)Ar.add(s);else if((l&we)!==0){var u=s;xe==null||xe.delete(u),(l&Zt)===0&&(l&qe&&(L===null||(L.f&kr)===0)&&(s.f|=Zt),Fi(u,at,n))}else if(f){var d=s;(l&nt)!==0&&it!==null&&it.add(d),n!==null?n.push(d):va(d)}}}let $r=!1,It=!1;function Vi(e){It=e}let F=null,ot=!1;function Xe(e){F=e}let L=null;function _t(e){L=e}let kt=null;function ji(e){F!==null&&(kt??(kt=new Set)).add(e)}let Ne=null,Be=0,Je=null;function al(e){Je=e}let Bi=1,rn=0,an=rn;function zi(e){an=e}function Ki(){return++Bi}function Jn(e){var t=e.f;if((t&fe)!==0)return!0;if(t&we&&(e.f&=~Zt),(t&at)!==0){for(var n=e.deps,r=n.length,a=0;a<r;a++){var o=n[a];if(Jn(o)&&Pi(o),o.wv>e.wv)return!0}(t&qe)!==0&&xe===null&&ce(e,pe)}return!1}function Yi(e,t,n=!0){var r=e.reactions;if(r!==null&&!(kt!==null&&kt.has(e)))for(var a=0;a<r.length;a++){var o=r[a];(o.f&we)!==0?Yi(o,t,!1):t===o&&(n?ce(o,fe):(o.f&pe)!==0&&ce(o,at),va(o))}}function Gi(e){var t=Ne,n=Be,r=Je,a=F,o=kt,s=Ce,l=ot,f=an,u=e.f;Ne=null,Be=0,Je=null,F=(u&(rt|vt))===0?e:null,kt=null,wn(e.ctx),ot=!1,an=++rn,e.ac!==null&&(En(()=>{e.ac.abort(Fn)}),e.ac=null);try{e.f|=kr;var d=e.fn,m=d();e.f|=Xt;var g=qi(e);if(pi()&&Je!==null&&!ot&&g!==null&&(e.f&(we|at|fe))===0)for(var p=0;p<Je.length;p++)Yi(Je[p],e);if(a!==null&&a!==e){if(rn++,a.deps!==null)for(let b=0;b<n;b+=1)a.deps[b].rv=rn;if(t!==null)for(const b of t)b.rv=rn;Je!==null&&(r===null?r=Je:r.push(...Je))}return(e.f&Vt)!==0&&(e.f^=Vt),m}catch(b){return qi(e),Ks(b)}finally{e.f^=kr,Ne=t,Be=n,Je=r,F=a,kt=o,wn(s),ot=l,an=f}}function qi(e){var a;var t=e.deps,n=T==null?void 0:T.is_fork;if(Ne!==null){var r;if(n||Zn(e,Be),t!==null&&Be>0)for(t.length=Be+Ne.length,r=0;r<Ne.length;r++)t[Be+r]=Ne[r];else e.deps=t=Ne;if(pa()&&(e.f&qe)!==0)for(r=Be;r<t.length;r++)((a=t[r]).reactions??(a.reactions=[])).push(e)}else!n&&t!==null&&Be<t.length&&(Zn(e,Be),t.length=Be);return t}function il(e,t){let n=t.reactions;if(n!==null){var r=ve.call(n,e);if(r!==-1){var a=n.length-1;a===0?n=t.reactions=null:(n[r]=n[a],n.pop())}}if(n===null&&(t.f&we)!==0&&(Ne===null||!Wt.call(Ne,t))){var o=t;(o.f&qe)!==0&&(o.f^=qe,o.f&=~Zt),o.v!==he&&oa(o),o.ac!==null&&En(()=>{o.ac.abort(Fn),o.ac=null,ce(o,fe)}),el(o),Zn(o,0)}}function Zn(e,t){var n=e.deps;if(n!==null)for(var r=t;r<n.length;r++)il(e,n[r])}function An(e){var t=e.f;if((t&je)===0){ce(e,pe);var n=L,r=$r;L=e,$r=(t&(rt|vt))===0;try{(t&(nt|qr))!==0?fl(e):ma(e),Qi(e);var a=Gi(e);e.teardown=typeof a=="function"?a:null,e.wv=Bi;var o;J&&Ls&&(e.f&fe)!==0&&e.deps}finally{$r=r,L=n}}}async function on(){await Promise.resolve(),Q()}function i(e){var t=e.f,n=(t&we)!==0;if(F!==null&&!ot){var r=L!==null&&(L.f&je)!==0;if(!r&&(kt===null||!kt.has(e))){var a=F.deps;if((F.f&kr)!==0)e.rv<rn&&(e.rv=rn,Ne===null&&a!==null&&a[Be]===e?Be++:Ne===null?Ne=[e]:Ne.push(e));else{F.deps??(F.deps=[]),Wt.call(F.deps,e)||F.deps.push(e);var o=e.reactions;o===null?e.reactions=[F]:Wt.call(o,F)||o.push(F)}}}if(It&&wt.has(e))return wt.get(e);if(n){var s=e;if(It){var l=s.v;return((s.f&pe)===0&&s.reactions!==null||Xi(s))&&(l=ca(s)),wt.set(s,l),l}var f=(s.f&qe)===0&&!ot&&F!==null&&($r||(F.f&qe)!==0),u=(s.f&Xt)===0;Jn(s)&&(f&&(s.f|=qe),Pi(s)),f&&!u&&(Ii(s),Wi(s))}if(xe!=null&&xe.has(e))return xe.get(e);if((e.f&Vt)!==0)throw e.v;return e.v}function Wi(e){if(e.f|=qe,e.deps!==null)for(const t of e.deps)(t.reactions??(t.reactions=[])).push(e),(t.f&we)!==0&&(t.f&qe)===0&&(Ii(t),Wi(t))}function Xi(e){if(e.v===he)return!0;if(e.deps===null)return!1;for(const t of e.deps)if(wt.has(t)||(t.f&we)!==0&&Xi(t))return!0;return!1}function Qn(e){var t=ot;try{return ot=!0,e()}finally{ot=t}}function ol(e){L===null&&(F===null&&As(),Cs()),It&&Ss()}function sl(e,t){var n=t.last;n===null?t.last=t.first=e:(n.next=e,e.prev=n,t.last=e)}function st(e,t){var n=L;n!==null&&(n.f&We)!==0&&(e|=We);var r={ctx:Ce,deps:null,nodes:null,f:e|fe|qe,first:null,fn:t,last:null,next:null,parent:n,b:n&&n.b,prev:null,teardown:null,wv:0,ac:null};T==null||T.register_created_effect(r);var a=r;if((e&mn)!==0)Cn!==null?Cn.push(r):jt.ensure().schedule(r);else if(t!==null){try{An(r)}catch(s){throw Ee(r),s}a.deps===null&&a.teardown===null&&a.nodes===null&&a.first===a.last&&(a.f&Jt)===0&&(a=a.first,(e&nt)!==0&&(e&Ft)!==0&&a!==null&&(a.f|=Ft))}if(a!==null&&(a.parent=n,n!==null&&sl(a,n),F!==null&&(F.f&we)!==0&&(e&vt)===0)){var o=F;(o.effects??(o.effects=[])).push(a)}return r}function pa(){return F!==null&&!ot}function Rr(e){const t=st(yr,null);return ce(t,pe),t.teardown=e,t}function De(e){ol();var t=L.f,n=!F&&(t&rt)!==0&&Ce!==null&&!Ce.i;if(n){var r=Ce;(r.e??(r.e=[])).push(e)}else return Ji(e)}function Ji(e){return st(mn|ys,e)}function ll(e){jt.ensure();const t=st(vt|Jt,e);return()=>{Ee(t)}}function cl(e){jt.ensure();const t=st(vt|Jt,e);return(n={})=>new Promise(r=>{n.outro?tr(t,()=>{Ee(t),r(void 0)}):(Ee(t),r(void 0))})}function ga(e){return st(mn,e)}function ul(e){return st(bn|Jt,e)}function Pr(e,t=0){return st(yr|t,e)}function Ae(e,t=[],n=[],r=[]){$i(r,t,n,a=>{st(yr,()=>{e(...a.map(i))})})}function er(e,t=0){var n=st(nt|t,e);return n}function Zi(e,t=0){var n=st(qr|t,e);return n}function xt(e){return st(rt|Jt,e)}function Qi(e){var t=e.teardown;if(t!==null){const n=It,r=F;Vi(!0),Xe(null);try{t.call(null)}catch(a){yt(a,e.parent)}finally{Vi(n),Xe(r)}}}function ma(e,t=!1){var n=e.first;for(e.first=e.last=null;n!==null;){const a=n.ac;a!==null&&En(()=>{a.abort(Fn)});var r=n.next;(n.f&vt)!==0?n.parent=null:Ee(n,t),n=r}}function fl(e){for(var t=e.first;t!==null;){var n=t.next;(t.f&rt)===0&&Ee(t),t=n}}function Ee(e,t=!0){var n=!1;(t||(e.f&bs)!==0)&&e.nodes!==null&&e.nodes.end!==null&&(eo(e.nodes.start,e.nodes.end),n=!0),e.f|=wr,ma(e,t&&!n),Zn(e,0);var r=e.nodes&&e.nodes.t;if(r!==null)for(const o of r)o.stop();Qi(e),e.f^=wr,e.f|=je;var a=e.parent;a!==null&&a.first!==null&&to(e),e.next=e.prev=e.teardown=e.ctx=e.deps=e.fn=e.nodes=e.ac=e.b=null}function eo(e,t){for(;e!==null;){var n=e===t?null:bt(e);e.remove(),e=n}}function to(e){var t=e.parent,n=e.prev,r=e.next;n!==null&&(n.next=r),r!==null&&(r.prev=n),t!==null&&(t.first===e&&(t.first=r),t.last===e&&(t.last=n))}function tr(e,t,n=!0){var r=[];e.f|=Xr,no(e,r,!0);var a=()=>{n&&Ee(e),t&&t()},o=r.length;if(o>0){var s=()=>--o||a();for(var l of r)l.out(s)}else a()}function no(e,t,n){if((e.f&We)===0){e.f^=We;var r=e.nodes&&e.nodes.t;if(r!==null)for(const l of r)(l.is_global||n)&&t.push(l);for(var a=e.first;a!==null;){var o=a.next;if((a.f&vt)===0){var s=(a.f&Ft)!==0||(a.f&rt)!==0&&(e.f&nt)!==0;no(a,t,s?n:!1)}a=o}}}function ro(e){e.f&=~Xr,ao(e,!0)}function ao(e,t){if((e.f&Xr)===0&&(e.f&We)!==0){e.f^=We,(e.f&pe)===0&&(ce(e,fe),jt.ensure().schedule(e));for(var n=e.first;n!==null;){var r=n.next,a=(n.f&Ft)!==0||(n.f&rt)!==0;ao(n,a?t:!1),n=r}var o=e.nodes&&e.nodes.t;if(o!==null)for(const s of o)(s.is_global||t)&&s.in()}}function io(e,t){if(e.nodes)for(var n=e.nodes.start,r=e.nodes.end;n!==null;){var a=n===r?null:bt(n);t.append(n),n=a}}function hl(e){let t=0,n=Wn(0),r;return()=>{pa()&&(i(n),Pr(()=>(t===0&&(r=Qn(()=>e(()=>Xn(n)))),t+=1,()=>{pt(()=>{t-=1,t===0&&(r==null||r(),r=void 0,Xn(n))})})))}}function oo(e){const t={get:n=>Kn(t.store)[n],set:(n,r)=>{typeof n=="string"?Object.assign(Kn(t.store),{[n]:r}):Object.assign(Kn(t.store),n),t.store.set(Kn(t.store))},store:Gs(e)};return t}globalThis.$altcha=globalThis.$altcha||{algorithms:new Map,defaults:oo({}),i18n:oo({}),instances:new Set,plugins:new Set};const dl={ariaLinkLabel:"Altcha (official website)",cancel:"Cancel",enterCode:"Enter code",enterCodeAria:"Enter code you hear. Press Space to play audio.",enterCodeFromImage:"To proceed, please enter the code from the image below.",error:"Verification failed. Try again later.",expired:"Verification expired. Try again.",footer:'Protected by <a href="https://altcha.org/" target="_blank" rel="noopener" aria-label="Altcha (official website, opens in new tab)">ALTCHA</a>',getAudioChallenge:"Get an audio challenge",label:"I’m not a robot",loading:"Loading...",reload:"Reload",verify:"Verify",verificationRequired:"Verification required!",verified:"Verified",verifying:"Verifying...",waitAlert:"Verifying... please wait."};globalThis.$altcha.i18n.set("en",dl);const vl="5";typeof window<"u"&&((Vo=window.__svelte??(window.__svelte={})).v??(Vo.v=new Set)).add(vl);const sn=Symbol("events"),so=new Set,ba=new Set;function lo(e,t,n,r={}){function a(o){if(r.capture||_a.call(t,o),!o.cancelBubble)return En(()=>n==null?void 0:n.call(this,o))}return e.startsWith("pointer")||e.startsWith("touch")||e==="wheel"?pt(()=>{t.addEventListener(e,a,r)}):t.addEventListener(e,a,r),a}function ge(e,t,n,r,a){var o={capture:r,passive:a},s=lo(e,t,n,o);(t===document.body||t===window||t===document||t instanceof HTMLMediaElement)&&Rr(()=>{t.removeEventListener(e,s,o)})}function Ir(e,t,n){(t[sn]??(t[sn]={}))[e]=n}function Or(e){for(var t=0;t<e.length;t++)so.add(e[t]);for(var n of ba)n(e)}let ya=null,wa=!1;function _a(e){var y,M;var t=this,n=t.ownerDocument,r=e.type,a=((y=e.composedPath)==null?void 0:y.call(e))||[],o=a[0]||e.target;ya=e,wa||(wa=!0,setTimeout(()=>{wa=!1,ya=null}));var s=0,l=ya===e&&e[sn];if(l){var f=a.indexOf(l);if(f!==-1&&(t===document||t===window)){e[sn]=t;return}var u=a.indexOf(t);if(u===-1)return;f<=u&&(s=f)}if(o=a[s]||e.target,o!==t){pn(e,"currentTarget",{configurable:!0,get(){return o||n}});var d=F,m=L;Xe(null),_t(null);try{for(var g,p=[];o!==null&&o!==t;){try{var b=(M=o[sn])==null?void 0:M[r];b!=null&&(!o.disabled||e.target===o)&&b.call(o,e)}catch(P){g?p.push(P):g=P}if(e.cancelBubble)break;s++,o=s<a.length?a[s]:null}if(g){for(let P of p)queueMicrotask(()=>{throw P});throw g}}finally{e[sn]=t,delete e.currentTarget,Xe(d),_t(m)}}}const ka=((jo=globalThis==null?void 0:globalThis.window)==null?void 0:jo.trustedTypes)&&globalThis.window.trustedTypes.createPolicy("svelte-trusted-html",{createHTML:e=>e});function pl(e){return(ka==null?void 0:ka.createHTML(e))??e}function co(e){var t=ia("template");return t.innerHTML=pl(e.replaceAll("<!>","<!---->")),t.content}function ze(e,t){var n=L;n.nodes===null&&(n.nodes={start:e,end:t,a:null,t:null})}function oe(e,t){var n=(t&Ms)!==0,r=(t&Ns)!==0,a,o=!e.startsWith("<!>");return()=>{if(O)return ze(N,null),N;a===void 0&&(a=co(o?e:"<!>"+e),n||(a=Me(a)));var s=r||wi?document.importNode(a,!0):a.cloneNode(!0);if(n){var l=Me(s),f=s.lastChild;ze(l,f)}else ze(s,s);return s}}function gl(e,t,n="svg"){var r=!e.startsWith("<!>"),a=`<${n}>${r?e:"<!>"+e}</${n}>`,o;return()=>{if(O)return ze(N,null),N;if(!o){var s=co(a),l=Me(s);o=Me(l)}var f=o.cloneNode(!0);return ze(f,f),f}}function xa(e,t){return gl(e,t,"svg")}function Lr(e=""){if(!O){var t=mt(e+"");return ze(t,t),t}var n=N;return n.nodeType!==jn?(n.before(n=mt()),Re(n)):Er(n),ze(n,n),n}function uo(){if(O)return ze(N,null),N;var e=document.createDocumentFragment(),t=document.createComment(""),n=mt();return e.append(t,n),ze(t,n),e}function B(e,t){if(O){var n=L;((n.f&Xt)===0||n.nodes.end===null)&&(n.nodes.end=N),tn();return}e!==null&&e.before(t)}function ml(e){return e.endsWith("capture")&&e!=="gotpointercapture"&&e!=="lostpointercapture"}const bl=["beforeinput","click","change","dblclick","contextmenu","focusin","focusout","input","keydown","keyup","mousedown","mousemove","mouseout","mouseover","mouseup","pointerdown","pointermove","pointerout","pointerover","pointerup","touchend","touchmove","touchstart"];function yl(e){return bl.includes(e)}const wl={formnovalidate:"formNoValidate",ismap:"isMap",nomodule:"noModule",playsinline:"playsInline",readonly:"readOnly",defaultvalue:"defaultValue",defaultchecked:"defaultChecked",srcobject:"srcObject",novalidate:"noValidate",allowfullscreen:"allowFullscreen",disablepictureinpicture:"disablePictureInPicture",disableremoteplayback:"disableRemotePlayback"};function _l(e){return e=e.toLowerCase(),wl[e]??e}const kl=["touchstart","touchmove"];function xl(e){return kl.includes(e)}var El=Ft|Jt;function Sl(e,t,n,r){new Cl(e,t,n,r)}class Cl{constructor(t,n,r,a){I(this,W);S(this,"parent");S(this,"is_pending",!1);S(this,"transform_error");I(this,Ke);I(this,ir,O?N:null);I(this,Ze);I(this,un);I(this,Ie);I(this,Ye,null);I(this,Oe,null);I(this,Ge,null);I(this,Ct,null);I(this,fn,0);I(this,Yt,0);I(this,Ln,!1);I(this,or,new Set);I(this,sr,new Set);I(this,Mt,null);I(this,Vr,hl(()=>(R(this,Mt,Wn(h(this,fn))),()=>{R(this,Mt,null)})));var o;R(this,Ke,t),R(this,Ze,n),R(this,un,s=>{var l=L;l.b=this,l.f|=Wr,r(s)}),this.parent=L.b,this.transform_error=a??((o=this.parent)==null?void 0:o.transform_error)??(s=>s),R(this,Ie,er(()=>{if(O){const s=h(this,ir);tn();const l=s.data===fi;if(s.data.startsWith(hi)){const u=JSON.parse(s.data.slice(hi.length));U(this,W,fs).call(this,u)}else l?U(this,W,hs).call(this):U(this,W,us).call(this)}else U(this,W,ni).call(this)},El)),O&&R(this,Ke,N)}defer_effect(t){Ei(t,h(this,or),h(this,sr))}is_rendered(){return!this.is_pending&&(!this.parent||this.parent.is_rendered())}has_pending_snippet(){return!!h(this,Ze).pending}update_pending_count(t,n){U(this,W,ri).call(this,t,n),R(this,fn,h(this,fn)+t),!(!h(this,Mt)||h(this,Ln))&&(R(this,Ln,!0),pt(()=>{R(this,Ln,!1),h(this,Mt)&&Tr(h(this,Mt),h(this,fn))}))}get_effect_pending(){return h(this,Vr).call(this),i(h(this,Mt))}error(t){if(!h(this,Ze).onerror&&!h(this,Ze).failed)throw t;T!=null&&T.is_fork?(h(this,Ye)&&T.skip_effect(h(this,Ye)),h(this,Oe)&&T.skip_effect(h(this,Oe)),h(this,Ge)&&T.skip_effect(h(this,Ge)),T.oncommit(()=>{U(this,W,ai).call(this,t)})):U(this,W,ai).call(this,t)}}Ke=new WeakMap,ir=new WeakMap,Ze=new WeakMap,un=new WeakMap,Ie=new WeakMap,Ye=new WeakMap,Oe=new WeakMap,Ge=new WeakMap,Ct=new WeakMap,fn=new WeakMap,Yt=new WeakMap,Ln=new WeakMap,or=new WeakMap,sr=new WeakMap,Mt=new WeakMap,Vr=new WeakMap,W=new WeakSet,us=function(){try{R(this,Ye,xt(()=>h(this,un).call(this,h(this,Ke))))}catch(t){this.error(t)}},fs=function(t){const n=h(this,Ze).failed,{reset:r,invoke_onerror:a}=U(this,W,ti).call(this,t);pt(a),n&&R(this,Ge,xt(()=>{n(h(this,Ke),()=>t,()=>r)}))},ti=function(t){var n=!1,r=!1;const a=()=>{if(n){Bs();return}n=!0,r&&Os(),h(this,Ge)!==null&&tr(h(this,Ge),()=>{R(this,Ge,null)}),U(this,W,Gr).call(this,()=>{U(this,W,ni).call(this)})};return{reset:a,invoke_onerror:()=>{var s,l;try{r=!0,(l=(s=h(this,Ze)).onerror)==null||l.call(s,t,a),r=!1}catch(f){yt(f,h(this,Ie)&&h(this,Ie).parent)}}}},hs=function(){const t=h(this,Ze).pending;t&&(this.is_pending=!0,R(this,Oe,xt(()=>t(h(this,Ke)))),pt(()=>{var n=R(this,Ct,document.createDocumentFragment()),r=mt(),a=!1;if(n.append(r),R(this,Ye,U(this,W,Gr).call(this,()=>{try{return xt(()=>h(this,un).call(this,r))}catch(o){try{this.error(o),a=!0}catch(s){yt(s,h(this,Ie).parent)}return null}})),h(this,Ye)===null){R(this,Ct,null),a&&U(this,W,mr).call(this,T);return}h(this,Yt)===0&&(h(this,Ke).before(n),R(this,Ct,null),tr(h(this,Oe),()=>{R(this,Oe,null)}),U(this,W,mr).call(this,T))}))},ni=function(){try{if(this.is_pending=this.has_pending_snippet(),R(this,Yt,0),R(this,fn,0),R(this,Ye,xt(()=>{h(this,un).call(this,h(this,Ke))})),h(this,Yt)>0){var t=R(this,Ct,document.createDocumentFragment());io(h(this,Ye),t);const n=h(this,Ze).pending;R(this,Oe,xt(()=>n(h(this,Ke))))}else U(this,W,mr).call(this,T)}catch(n){this.error(n)}},mr=function(t){this.is_pending=!1,t.transfer_effects(h(this,or),h(this,sr))},Gr=function(t){var n=L,r=F,a=Ce;_t(h(this,Ie)),Xe(h(this,Ie)),wn(h(this,Ie).ctx);try{return jt.ensure(),t()}finally{_t(n),Xe(r),wn(a)}},ri=function(t,n){var r;if(!this.has_pending_snippet()){this.parent&&U(r=this.parent,W,ri).call(r,t,n);return}R(this,Yt,h(this,Yt)+t),h(this,Yt)===0&&(U(this,W,mr).call(this,n),h(this,Oe)&&tr(h(this,Oe),()=>{R(this,Oe,null)}),h(this,Ct)&&(h(this,Ke).before(h(this,Ct)),R(this,Ct,null)))},ai=function(t){h(this,Ye)&&(Ee(h(this,Ye)),R(this,Ye,null)),h(this,Oe)&&(Ee(h(this,Oe)),R(this,Oe,null)),h(this,Ge)&&(Ee(h(this,Ge)),R(this,Ge,null)),O&&(Re(h(this,ir)),ta(),Re(na()));let n=h(this,Ze).failed;const r=a=>{const{reset:o,invoke_onerror:s}=U(this,W,ti).call(this,a);s(),n&&R(this,Ge,U(this,W,Gr).call(this,()=>{try{return xt(()=>{var l=L;l.b=this,l.f|=Wr,n(h(this,Ke),()=>a,()=>o)})}catch(l){return yt(l,h(this,Ie).parent),null}}))};pt(()=>{var a;try{a=this.transform_error(t)}catch(o){yt(o,h(this,Ie)&&h(this,Ie).parent);return}a!==null&&typeof a=="object"&&typeof a.then=="function"?a.then(r,o=>yt(o,h(this,Ie)&&h(this,Ie).parent)):r(a)})};function Et(e,t){var n=t==null?"":typeof t=="object"?`${t}`:t;n!==(e[Un]??(e[Un]=e.nodeValue))&&(e[Un]=n,e.nodeValue=`${n}`)}function fo(e,t){return ho(e,t)}function Al(e,t){aa(),t.intro=t.intro??!1;const n=t.target,r=O,a=N;try{for(var o=Me(n);o&&(o.nodeType!==Bn||o.data!==Qr);)o=bt(o);if(!o)throw Qt;gt(!0),Re(o);const s=ho(e,{...t,anchor:o});return gt(!1),s}catch(s){if(s instanceof Error&&s.message.split(`
`).some(l=>l.startsWith("https://svelte.dev/e/")))throw s;return s!==Qt&&console.warn("Failed to hydrate: ",s),t.recover===!1&&$s(),aa(),zs(n),gt(!1),fo(e,t)}finally{gt(r),Re(a)}}const Mr=new Map;function ho(e,{target:t,anchor:n,props:r={},events:a,context:o,intro:s=!0,transformError:l}){aa();var f=void 0,u=cl(()=>{var d=n??t.appendChild(mt());Sl(d,{pending:()=>{}},p=>{$t({});var b=Ce;if(o&&(b.c=o),a&&(r.$$events=a),O&&ze(p,null),f=e(p,r)||ea(),O&&(L.nodes.end=N,N===null||N.nodeType!==Bn||N.data!==di))throw zn(),Qt;Rt()},l);var m=new Set,g=p=>{for(var b=0;b<p.length;b++){var y=p[b];if(!m.has(y)){m.add(y);var M=xl(y);for(const se of[t,document]){var P=Mr.get(se);P===void 0&&(P=new Map,Mr.set(se,P));var z=P.get(y);z===void 0?(se.addEventListener(y,_a,{passive:M}),P.set(y,1)):P.set(y,z+1)}}}};return g(ds(so)),ba.add(g),()=>{var M;for(var p of m)for(const P of[t,document]){var b=Mr.get(P),y=b.get(p);--y==0?(P.removeEventListener(p,_a),b.delete(p),b.size===0&&Mr.delete(P)):b.set(p,y)}ba.delete(g),d!==n&&((M=d.parentNode)==null||M.removeChild(d))}});return Ea.set(f,u),f}let Ea=new WeakMap;function Tl(e,t){const n=Ea.get(e);return n?(Ea.delete(e),n(t)):Promise.resolve()}class Nr{constructor(t,n=!0){S(this,"anchor");I(this,ct,new Map);I(this,Nt,new Map);I(this,ut,new Map);I(this,hn,new Set);I(this,lr,!0);I(this,jr,t=>{if(h(this,ct).has(t)){var n=h(this,ct).get(t),r=h(this,Nt).get(n);if(r)ro(r),h(this,hn).delete(n);else{var a=h(this,ut).get(n);a&&(ro(a.effect),h(this,Nt).set(n,a.effect),h(this,ut).delete(n),a.fragment.lastChild.remove(),this.anchor.before(a.fragment),r=a.effect)}for(const[o,s]of h(this,ct)){if(h(this,ct).delete(o),o===t)break;const l=h(this,ut).get(s);l&&(Ee(l.effect),h(this,ut).delete(s))}for(const[o,s]of h(this,Nt)){if(o===n||h(this,hn).has(o))continue;const l=()=>{if(Array.from(h(this,ct).values()).includes(o)){var u=document.createDocumentFragment();io(s,u),u.append(mt()),h(this,ut).set(o,{effect:s,fragment:u})}else Ee(s);h(this,hn).delete(o),h(this,Nt).delete(o)};h(this,lr)||!r?(h(this,hn).add(o),tr(s,l,!1)):l()}}});I(this,Fa,t=>{h(this,ct).delete(t);const n=Array.from(h(this,ct).values());for(const[r,a]of h(this,ut))n.includes(r)||(Ee(a.effect),h(this,ut).delete(r))});this.anchor=t,R(this,lr,n)}ensure(t,n){var r=T;n&&!h(this,Nt).has(t)&&!h(this,ut).has(t)&&h(this,Nt).set(t,xt(()=>n(this.anchor))),h(this,ct).set(r,t),O&&(this.anchor=N),h(this,jr).call(this,r)}}ct=new WeakMap,Nt=new WeakMap,ut=new WeakMap,hn=new WeakMap,lr=new WeakMap,jr=new WeakMap,Fa=new WeakMap;function $l(e,t,...n){var r=new Nr(e);er(()=>{const a=t()??null;r.ensure(a,a&&(o=>a(o,...n)))},Ft)}function Sa(e){Ce===null&&xs(),De(()=>{const t=Qn(e);if(typeof t=="function")return t})}function me(e,t,n=!1){var r;O&&(r=N,tn());var a=new Nr(e),o=n?Ft:0;function s(l,f){if(O){var u=mi(r);if(l!==parseInt(u.substring(1))){var d=na();Re(d),a.anchor=d,gt(!1),a.ensure(l,f),gt(!0);return}}a.ensure(l,f)}er(()=>{var l=!1;t((f,u=0)=>{l=!0,s(u,f)}),l||s(-1,null)},o)}const Rl=Symbol("NaN");function Pl(e,t,n){O&&tn();var r=new Nr(e);er(()=>{var a=t();a!==a&&(a=Rl),r.ensure(a,n)})}function vo(e,t,n=!1,r=!1,a=!1,o=!1){var s=e,l="";if(n){var f=e;O&&(s=Re(Me(f)))}Ae(()=>{var u=L;if(l===(l=t()??"")){O&&tn();return}if(n&&!O){u.nodes=null,f.innerHTML=l,l!==""&&ze(Me(f),f.lastChild);return}if(u.nodes!==null&&(eo(u.nodes.start,u.nodes.end),u.nodes=null),l!==""){if(O){N.data;for(var d=tn(),m=d;d!==null&&(d.nodeType!==Bn||d.data!=="");)m=d,d=bt(d);if(d===null)throw zn(),Qt;ze(N,m),s=Re(d);return}var g=r?Ds:a?Us:void 0,p=ia(r?"svg":a?"math":"template",g);p.innerHTML=l;var b=r||a?p:p.content;if(ze(Me(b),b.lastChild),r||a)for(;Me(b);)s.before(Me(b));else s.before(b)}})}function Il(e,t,n){var r;O&&(r=N,tn());var a=new Nr(e);er(()=>{var o=t()??null;if(O){var s=mi(r),l=s===Qr,f=o!==null;if(l!==f){var u=na();Re(u),a.anchor=u,gt(!1),a.ensure(o,o&&(d=>n(d,o))),gt(!0);return}}a.ensure(o,o&&(d=>n(d,o)))},Ft)}function Ol(e,t){var n=void 0,r;Zi(()=>{n!==(n=t())&&(r&&(Ee(r),r=null),n&&(r=xt(()=>{ga(()=>n(e))})))})}function po(e){var t,n,r="";if(typeof e=="string"||typeof e=="number")r+=e;else if(typeof e=="object")if(Array.isArray(e)){var a=e.length;for(t=0;t<a;t++)e[t]&&(n=po(e[t]))&&(r&&(r+=" "),r+=n)}else for(n in e)e[n]&&(r&&(r+=" "),r+=n);return r}function Ll(){for(var e,t,n=0,r="",a=arguments.length;n<a;n++)(e=arguments[n])&&(t=po(e))&&(r&&(r+=" "),r+=t);return r}function Ml(e){return typeof e=="object"?Ll(e):e??""}const go=[...` 	
\r\f \v\uFEFF`];function Nl(e,t,n){var r=e==null?"":""+e;if(n){for(var a of Object.keys(n))if(n[a])r=r?r+" "+a:a;else if(r.length)for(var o=a.length,s=0;(s=r.indexOf(a,s))>=0;){var l=s+o;(s===0||go.includes(r[s-1]))&&(l===r.length||go.includes(r[l]))?r=(s===0?"":r.substring(0,s))+r.substring(l+1):s=l}}return r===""?null:r}function mo(e,t=!1){var n=t?" !important;":";",r="";for(var a of Object.keys(e)){var o=e[a];o!=null&&o!==""&&(r+=" "+a+": "+o+n)}return r}function Ca(e){return e[0]!=="-"||e[1]!=="-"?e.toLowerCase():e}function Dl(e,t){if(t){var n="",r,a;if(Array.isArray(t)?(r=t[0],a=t[1]):r=t,e){e=String(e).replaceAll(/\/\*.*?\*\//g,"").trim();var o=!1,s=0,l=!1,f=[];r&&f.push(...Object.keys(r).map(Ca)),a&&f.push(...Object.keys(a).map(Ca));var u=0,d=-1;const y=e.length;for(var m=0;m<y;m++){var g=e[m];if(l?g==="/"&&e[m-1]==="*"&&(l=!1):o?o===g&&(o=!1):g==="/"&&e[m+1]==="*"?l=!0:g==='"'||g==="'"?o=g:g==="("?s++:g===")"&&s--,!l&&o===!1&&s===0){if(g===":"&&d===-1)d=m;else if(g===";"||m===y-1){if(d!==-1){var p=Ca(e.substring(u,d).trim());if(!f.includes(p)){g!==";"&&m++;var b=e.substring(u,m).trim();n+=" "+b+";"}}u=m+1,d=-1}}}}return r&&(n+=mo(r)),a&&(n+=mo(a,!0)),n=n.trim(),n===""?null:n}return e==null?null:String(e)}function Ul(e,t,n,r,a,o){var s=e[Jr];if(O||s!==n||s===void 0){var l=Nl(n,r,o);(!O||l!==e.getAttribute("class"))&&(l==null?e.removeAttribute("class"):t?e.className=l:e.setAttribute("class",l)),e[Jr]=n}else if(o&&a!==o)for(var f in o){var u=!!o[f];(a==null||u!==!!a[f])&&e.classList.toggle(f,u)}return o}function Aa(e,t={},n,r){for(var a in n){var o=n[a];t[a]!==o&&(n[a]==null?e.style.removeProperty(a):e.style.setProperty(a,o,r))}}function Hl(e,t,n,r){var a=e[Zr];if(O||a!==t){var o=Dl(t,r);(!O||o!==e.getAttribute("style"))&&(o==null?e.removeAttribute("style"):e.style.cssText=o),e[Zr]=t}else r&&(Array.isArray(r)?(Aa(e,n==null?void 0:n[0],r[0]),Aa(e,n==null?void 0:n[1],r[1],"important")):Aa(e,n,r));return r}function bo(e,t){t?e.hasAttribute("selected")||e.setAttribute("selected",""):e.removeAttribute("selected")}function yo(e,t){var n=!("__defaultValue"in e);!n&&e.__defaultValue===t||(e.__defaultValue=t,wo(e,!n||"__value"in e))}function wo(e,t){var n=e.__defaultValue,r=e.multiple,a=r?n??[]:null;if(!(r&&!Z(a))){var o=e.selectedIndex,s=t&&r?new Set(e.selectedOptions):null;for(var l of e.options){var f=$a(l);bo(l,r?a.includes(f):yi(f,n))}if(t)if(s!==null)for(l of e.options){var u=s.has(l);l.selected!==u&&(l.selected=u)}else e.selectedIndex!==o&&(e.selectedIndex=o)}}function Ta(e,t,n=!1){if(e.multiple){if(t==null)return;if(!Z(t))return js();for(var r of e.options)r.selected=t.includes($a(r));return}for(r of e.options){var a=$a(r);if(yi(a,t)){r.selected=!0;return}}(!n||t!==void 0)&&(e.selectedIndex=-1)}function Fl(e){var t=new MutationObserver(n=>{n.every(Vl)||("__defaultValue"in e&&wo(e,!1),"__value"in e&&Ta(e,e.__value))});t.observe(e,{childList:!0,subtree:!0,attributes:!0,attributeFilter:["value"]}),Rr(()=>{t.disconnect()})}function $a(e){return"__value"in e?e.__value:e.value}function Vl(e){if(e.target.closest("selectedcontent")!==null)return!0;if(e.type==="childList"){var t=[...e.addedNodes,...e.removedNodes];return t.length>0&&t.every(n=>n.nodeName==="SELECTEDCONTENT")}return!1}const nr=Symbol("class"),rr=Symbol("style"),_o=Symbol("is custom element"),ko=Symbol("is html"),jl=Vn?"link":"LINK",xo=Vn?"input":"INPUT",Bl=Vn?"option":"OPTION",Eo=Vn?"select":"SELECT",zl=Vn?"progress":"PROGRESS";function Ra(e){if(O){var t=!1,n=()=>{if(!t){if(t=!0,e.hasAttribute("value")){var r=e.value;K(e,"value",null),e.value=r}if(e.hasAttribute("checked")){var a=e.checked;K(e,"checked",null),e.checked=a}}};e[Hn]=n,pt(n),Ti()}}function Kl(e,t){var n=Pa(e);n.value===(n.value=t??void 0)||e.value===t&&(t!==0||e.nodeName!==zl)||(e.value=t??"")}function K(e,t,n,r){var a=Pa(e);O&&(a[t]=e.getAttribute(t),t==="src"||t==="srcset"||t==="href"&&e.nodeName===jl)||a[t]!==(a[t]=n)&&(t==="loading"&&(e[_s]=n),n==null?e.removeAttribute(t):typeof n!="string"&&Co(e).has(t)?e[t]=n:e.setAttribute(t,n))}function Yl(e,t,n,r,a=!1,o=!1){O&&a&&e.nodeName===xo&&("defaultValue"in n||"defaultChecked"in n||Ra(e));var s=Pa(e),l=s[_o],f=!s[ko];let u=O&&l;u&&gt(!1);var d=t||{},m=e.nodeName===Bl,g=e.nodeName===Eo;for(var p in t)!(p in n)&&p[0]+p[1]!=="$$"&&(n[p]=null);n.class?n.class=Ml(n.class):n[nr]&&(n.class=null),n[rr]&&(n.style??(n.style=null));var b=Co(e);if(e.nodeName===xo&&"type"in n&&("value"in n||"__value"in n)){var y=n.type;(y!==d.type||y===void 0&&e.hasAttribute("type"))&&(d.type=y,K(e,"type",y))}for(const Y in n){let $=n[Y];if(m&&Y==="value"&&$==null){e.value=e.__value="",d[Y]=$;continue}if(Y==="class"){var M=e.namespaceURI==="http://www.w3.org/1999/xhtml";Ul(e,M,$,r,t==null?void 0:t[nr],n[nr]),d[Y]=$,d[nr]=n[nr];continue}if(Y==="style"){Hl(e,$,t==null?void 0:t[rr],n[rr]),d[Y]=$,d[rr]=n[rr];continue}var P=d[Y];if(!($===P&&!($===void 0&&e.hasAttribute(Y)))){d[Y]=$;var z=Y[0]+Y[1];if(z!=="$$")if(z==="on"){const te={},$e="$$"+Y;let D=Y.slice(2);var se=yl(D);if(ml(D)&&(D=D.slice(0,-7),te.capture=!0),!se&&P){if($!=null)continue;e.removeEventListener(D,d[$e],te),d[$e]=null}if(se)Ir(D,e,$),Or([D]);else if($!=null){let be=function(et){d[Y].call(this,et)};d[$e]=lo(D,e,be,te)}}else if(Y==="style")K(e,Y,$);else if(Y==="autofocus")Ws(e,!!$);else if(!l&&(Y==="__value"||Y==="value"&&$!=null))e.value=e.__value=$;else if(Y==="selected"&&m)bo(e,$);else{var j=Y;f||(j=_l(j));var Te=j==="defaultValue"||j==="defaultChecked";if(g&&j==="defaultValue")continue;if($==null&&!l&&!Te)if(s[Y]=null,j==="value"||j==="checked"){let te=e;const $e=t===void 0;if(j==="value"){let D=te.defaultValue;te.removeAttribute(j),te.defaultValue=D,te.value=te.__value=$e?D:null}else{let D=te.defaultChecked;te.removeAttribute(j),te.defaultChecked=D,te.checked=$e?D:!1}}else e.removeAttribute(Y);else Te||(l||typeof $!="string")&&b.has(j)?(e[j]=$,j in s&&(s[j]=he)):typeof $!="function"&&K(e,j,$)}}}return u&&gt(!0),d}function Dr(e,t,n=[],r=[],a=[],o,s=!1,l=!1){$i(a,n,r,f=>{var u=void 0,d={},m=e.nodeName===Eo,g=!1;if(Zi(()=>{var b=t(...f.map(i)),y=Yl(e,u,b,o,s,l);if(g&&m){var M=e;"defaultValue"in b&&yo(M,b.defaultValue),"value"in b&&Ta(M,b.value)}for(let z of Object.getOwnPropertySymbols(d))b[z]||Ee(d[z]);for(let z of Object.getOwnPropertySymbols(b)){var P=b[z];z.description===Hs&&(!u||P!==u[z])&&(d[z]&&Ee(d[z]),d[z]=xt(()=>Ol(e,()=>P))),y[z]=P}u=y}),m){var p=e;ga(()=>{var b=u;"defaultValue"in b&&yo(p,b.defaultValue),Ta(p,b.value,!0),Fl(p)})}g=!0})}function Pa(e){return e[xr]??(e[xr]={[_o]:e.nodeName.includes("-"),[ko]:e.namespaceURI===vi})}var So=new Map;function Co(e){var t=e.getAttribute("is")||e.nodeName,n=So.get(t);if(n)return n;So.set(t,n=new Set);for(var r,a=e,o=Element.prototype;o!==a;){r=vs(a);for(var s in r)r[s].set&&s!=="innerHTML"&&s!=="textContent"&&s!=="innerText"&&n.add(s);a=ii(a)}return n}function Gl(e,t,n=t){var r=new WeakSet;Xs(e,"input",async a=>{var o=a?e.defaultValue:e.value;if(o=Ia(e)?Oa(o):o,n(o),T!==null&&r.add(T),await on(),o!==(o=t())){var s=e.selectionStart,l=e.selectionEnd,f=e.value.length;if(e.value=o??"",l!==null){var u=e.value.length;s===l&&l===f&&u>f?(e.selectionStart=u,e.selectionEnd=u):(e.selectionStart=s,e.selectionEnd=Math.min(l,u))}}}),(O&&e.defaultValue!==e.value||Qn(t)==null&&e.value)&&(n(Ia(e)?Oa(e.value):e.value),T!==null&&r.add(T)),Pr(()=>{var a=t();if(e===document.activeElement){var o=T;if(r.has(o))return}Ia(e)&&a===Oa(e.value)||e.type==="date"&&!a&&!e.value||a!==e.value&&(e.value=a??"")})}function Ia(e){var t=e.type;return t==="number"||t==="range"}function Oa(e){return e===""?null:+e}function La(e,t){return e===t||(e==null?void 0:e[yn])===t}function Bt(e=ea(),t,n,r){var a=Ce.r,o=L;return ga(()=>{var s,l;return Pr(()=>{s=l,l=[],Qn(()=>{La(n(...l),e)||(t(e,...l),s&&La(n(...s),e)&&t(null,...s))})}),()=>{let f=o;for(;f!==a&&f.parent!==null&&f.parent.f&wr;)f=f.parent;const u=()=>{l&&La(n(...l),e)&&t(null,...l)},d=f.teardown;f.teardown=()=>{u(),d==null||d()}}}),e}const ql={get(e,t){if(!e.exclude.has(t))return e.props[t]},set(e,t){return!1},getOwnPropertyDescriptor(e,t){if(!e.exclude.has(t)&&t in e.props)return{enumerable:!0,configurable:!0,value:e.props[t]}},has(e,t){return e.exclude.has(t)?!1:t in e.props},ownKeys(e){return Reflect.ownKeys(e.props).filter(t=>!e.exclude.has(t))}};function Ur(e,t,n){return new Proxy({props:e,exclude:t},ql)}function ae(e,t,n,r){var a=r,o=!0,s=()=>(o&&(o=!1,a=r),a),l;l=e[t],l===void 0&&r!==void 0&&(l=s());var f;f=()=>{var g=e[t];return g===void 0?s():(o=!0,g)};var u=!1,d=la(()=>(u=!1,f())),m=L;return(function(g,p){if(arguments.length>0){const b=p?i(d):g;return E(d,b),u=!0,a!==void 0&&(a=b),g}return It&&u||(m.f&je)!==0?d.v:i(d)})}function Wl(e){return new Xl(e)}class Xl{constructor(t){I(this,Dt);I(this,Qe);var o;var n=new Map,r=(s,l)=>{var f=Hi(l,!1,!1);return n.set(s,f),f};const a=new Proxy({...t.props||{},$$events:{}},{get(s,l){return i(n.get(l)??r(l,Reflect.get(s,l)))},has(s,l){return l===ws?!0:(i(n.get(l)??r(l,Reflect.get(s,l))),Reflect.has(s,l))},set(s,l,f){return E(n.get(l)??r(l,f),f),Reflect.set(s,l,f)}});R(this,Qe,(t.hydrate?Al:fo)(t.component,{target:t.target,anchor:t.anchor,props:a,context:t.context,intro:t.intro??!1,recover:t.recover,transformError:t.transformError})),(!((o=t==null?void 0:t.props)!=null&&o.$$host)||t.sync===!1)&&Q(),R(this,Dt,a.$$events);for(const s of Object.keys(h(this,Qe)))s==="$set"||s==="$destroy"||s==="$on"||pn(this,s,{get(){return h(this,Qe)[s]},set(l){h(this,Qe)[s]=l},enumerable:!0});h(this,Qe).$set=s=>{Object.assign(a,s)},h(this,Qe).$destroy=()=>{Tl(h(this,Qe))}}$set(t){h(this,Qe).$set(t)}$on(t,n){h(this,Dt)[t]=h(this,Dt)[t]||[];const r=(...a)=>n.call(this,...a);return h(this,Dt)[t].push(r),()=>{h(this,Dt)[t]=h(this,Dt)[t].filter(a=>a!==r)}}$destroy(){h(this,Qe).$destroy()}}Dt=new WeakMap,Qe=new WeakMap;let Ao=class{};typeof HTMLElement=="function"&&(Ao=class extends HTMLElement{constructor(t,n,r){super();S(this,"$$ctor");S(this,"$$s");S(this,"$$c");S(this,"$$cn",!1);S(this,"$$d",{});S(this,"$$r",!1);S(this,"$$p_d",{});S(this,"$$l",{});S(this,"$$l_u",new Map);S(this,"$$me");S(this,"$$shadowRoot",null);this.$$ctor=t,this.$$s=n,r&&(this.$$shadowRoot=this.attachShadow(r))}addEventListener(t,n,r){if(this.$$l[t]=this.$$l[t]||[],this.$$l[t].push(n),this.$$c){const a=this.$$c.$on(t,n);this.$$l_u.set(n,a)}super.addEventListener(t,n,r)}removeEventListener(t,n,r){if(super.removeEventListener(t,n,r),this.$$c){const a=this.$$l_u.get(n);a&&(a(),this.$$l_u.delete(n))}}async connectedCallback(){if(this.$$cn=!0,!this.$$c){let t=function(a){return o=>{const s=ia("slot");a!=="default"&&(s.name=a),B(o,s)}};if(await Promise.resolve(),!this.$$cn||this.$$c)return;const n={},r=Jl(this);for(const a of this.$$s)a in r&&(a==="default"&&!this.$$d.children?(this.$$d.children=t(a),n.default=!0):n[a]=t(a));for(const a of this.attributes){const o=this.$$g_p(a.name);o in this.$$d||(this.$$d[o]=Hr(o,a.value,this.$$p_d,"toProp"))}for(const a in this.$$p_d)!(a in this.$$d)&&this[a]!==void 0&&(this.$$d[a]=this[a],delete this[a]);this.$$c=Wl({component:this.$$ctor,target:this.$$shadowRoot||this,props:{...this.$$d,$$slots:n,$$host:this}}),this.$$me=ll(()=>{Pr(()=>{var a;this.$$r=!0;for(const o of br(this.$$c)){if(!((a=this.$$p_d[o])!=null&&a.reflect))continue;this.$$d[o]=this.$$c[o];const s=Hr(o,this.$$d[o],this.$$p_d,"toAttribute");s==null?this.removeAttribute(this.$$p_d[o].attribute||o):this.setAttribute(this.$$p_d[o].attribute||o,s)}this.$$r=!1})});for(const a in this.$$l)for(const o of this.$$l[a]){const s=this.$$c.$on(a,o);this.$$l_u.set(o,s)}this.$$l={}}}attributeChangedCallback(t,n,r){var a;this.$$r||(t=this.$$g_p(t),this.$$d[t]=Hr(t,r,this.$$p_d,"toProp"),(a=this.$$c)==null||a.$set({[t]:this.$$d[t]}))}disconnectedCallback(){this.$$cn=!1,Promise.resolve().then(()=>{!this.$$cn&&this.$$c&&(this.$$c.$destroy(),this.$$me(),this.$$c=void 0)})}$$g_p(t){return br(this.$$p_d).find(n=>this.$$p_d[n].attribute===t||!this.$$p_d[n].attribute&&n.toLowerCase()===t)||t}});function Hr(e,t,n,r){var o;const a=(o=n[e])==null?void 0:o.type;if(t=a==="Boolean"&&typeof t!="boolean"?t!=null:t,!r||!n[e])return t;if(r==="toAttribute")switch(a){case"Object":case"Array":return t==null?null:JSON.stringify(t);case"Boolean":return t?"":null;case"Number":return t??null;default:return t}else switch(a){case"Object":case"Array":return t&&JSON.parse(t);case"Boolean":return t;case"Number":return t!=null?+t:t;default:return t}}function Jl(e){const t={};return e.childNodes.forEach(n=>{t[n.slot||"default"]=!0}),t}function zt(e,t,n,r,a,o){let s=class extends Ao{constructor(){super(e,n,a),this.$$p_d=t}static get observedAttributes(){return br(t).map(l=>(t[l].attribute||l).toLowerCase())}};return br(t).forEach(l=>{pn(s.prototype,l,{get(){return this.$$c&&l in this.$$c?this.$$c[l]:this.$$d[l]},set(f){var m;f=Hr(l,f,t),this.$$d[l]=f;var u=this.$$c;if(u){var d=(m=gn(u,l))==null?void 0:m.get;d?u[l]=f:u.$set({[l]:f})}}})}),r.forEach(l=>{pn(s.prototype,l,{get(){var f;return(f=this.$$c)==null?void 0:f[l]}})}),e.element=s,s}var Zl=new Set(["$$slots","$$events","$$legacy","$$host","loading"]),Ql=oe('<div class="altcha-checkbox"><input/> <svg aria-hidden="true" width="12" height="9" viewBox="0 0 12 9"><polyline points="1 5 4 8 11 1"></polyline></svg> <div class="altcha-spinner altcha-checkbox-spinner" aria-hidden="true"></div></div>');function To(e,t){$t(t,!0);let n=ae(t,"loading"),r=Ur(t,Zl),a;function o(){a==null||a.click()}var s={get loading(){return n()},set loading(d){n(d),Q()}},l=Ql(),f=_e(l);Dr(f,()=>({type:"checkbox",...r}),void 0,void 0,void 0,void 0,!0),Bt(f,d=>a=d,()=>a);var u=re(f,2);return ta(2),de(l),Ae(()=>K(l,"data-loading",n())),Ir("click",u,o),B(e,l),Rt(s)}Or(["click"]),zt(To,{loading:{}},[],[],{mode:"open"});var ec=new Set(["$$slots","$$events","$$legacy","$$host","loading"]),tc=oe('<div class="altcha-checkbox-native"><input/> <div class="altcha-spinner altcha-checkbox-native-spinner"></div></div>');function $o(e,t){$t(t,!0);let n=ae(t,"loading"),r=Ur(t,ec);var a={get loading(){return n()},set loading(l){n(l),Q()}},o=tc(),s=_e(o);return Dr(s,()=>({type:"checkbox",...r}),void 0,void 0,void 0,void 0,!0),ta(2),de(o),Ae(()=>K(o,"data-loading",n())),B(e,o),Rt(a)}zt($o,{loading:{}},[],[],{mode:"open"});var nc=oe('<div><a target="_blank" rel="noopener" class="altcha-logo" aria-hidden="true" tabindex="-1"><svg width="22" height="22" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.33955 16.4279C5.88954 20.6586 12.1971 21.2105 16.4279 17.6604C18.4699 15.947 19.6548 13.5911 19.9352 11.1365L17.9886 10.4279C17.8738 12.5624 16.909 14.6459 15.1423 16.1284C11.7577 18.9684 6.71167 18.5269 3.87164 15.1423C1.03163 11.7577 1.4731 6.71166 4.8577 3.87164C8.24231 1.03162 13.2883 1.4731 16.1284 4.8577C16.9767 5.86872 17.5322 7.02798 17.804 8.2324L19.9522 9.01429C19.7622 7.07737 19.0059 5.17558 17.6604 3.57212C14.1104 -0.658624 7.80283 -1.21043 3.57212 2.33956C-0.658625 5.88958 -1.21046 12.1971 2.33955 16.4279Z" fill="currentColor"></path><path d="M3.57212 2.33956C1.65755 3.94607 0.496389 6.11731 0.12782 8.40523L2.04639 9.13961C2.26047 7.15832 3.21057 5.25375 4.8577 3.87164C8.24231 1.03162 13.2883 1.4731 16.1284 4.8577L13.8302 6.78606L19.9633 9.13364C19.7929 7.15555 19.0335 5.20847 17.6604 3.57212C14.1104 -0.658624 7.80283 -1.21043 3.57212 2.33956Z" fill="currentColor"></path><path d="M7 10H5C5 12.7614 7.23858 15 10 15C12.7614 15 15 12.7614 15 10H13C13 11.6569 11.6569 13 10 13C8.3431 13 7 11.6569 7 10Z" fill="currentColor"></path></svg></a></div>');function Ma(e,t){$t(t,!0);let n=ae(t,"strings");const r="https://altcha.org";var a={get strings(){return n()},set strings(l){n(l),Q()}},o=nc(),s=_e(o);return K(s,"href",r),de(o),Ae(()=>K(s,"aria-label",n().ariaLinkLabel)),B(e,o),Rt(a)}zt(Ma,{strings:{}},[],[],{mode:"open"});var rc=oe('<div class="altcha-footer"><p></p> <!></div>');function Na(e,t){$t(t,!0);let n=ae(t,"logo"),r=ae(t,"strings");var a={get logo(){return n()},set logo(u){n(u),Q()},get strings(){return r()},set strings(u){r(u),Q()}},o=rc(),s=_e(o);vo(s,()=>r().footer,!0),de(s);var l=re(s,2);{var f=u=>{Ma(u,{get strings(){return r()}})};me(l,u=>{n()&&u(f)})}return de(o),B(e,o),Rt(a)}zt(Na,{logo:{},strings:{}},[],[],{mode:"open"});var ac=new Set(["$$slots","$$events","$$legacy","$$host","loading"]),ic=oe('<div class="altcha-switch"><input/>  <div class="altcha-switch-toggle"><div class="altcha-spinner altcha-switch-spinner"></div></div></div>');function Ro(e,t){$t(t,!0);let n=ae(t,"loading"),r=Ur(t,ac),a;function o(){a==null||a.click()}var s={get loading(){return n()},set loading(d){n(d),Q()}},l=ic(),f=_e(l);Dr(f,()=>({type:"checkbox",...r}),void 0,void 0,void 0,void 0,!0),Bt(f,d=>a=d,()=>a);var u=re(f,2);return de(l),Ae(()=>K(l,"data-loading",n())),Ir("click",u,o),B(e,l),Rt(s)}Or(["click"]),zt(Ro,{loading:{}},[],[],{mode:"open"});var Se=(e=>(e.ERROR="error",e.LOADING="loading",e.PLAYING="playing",e.PAUSED="paused",e.READY="ready",e))(Se||{}),Po=(e=>(e.SHA_256="SHA-256",e.SHA_384="SHA-384",e.SHA_512="SHA-512",e))(Po||{}),X=(e=>(e.CODE="code",e.ERROR="error",e.VERIFIED="verified",e.VERIFYING="verifying",e.UNVERIFIED="unverified",e.EXPIRED="expired",e))(X||{}),oc=oe('<div class="altcha-code-challenge-title"> </div>'),sc=oe('<div class="altcha-spinner"></div>'),lc=xa('<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12.8659 3.00017L22.3922 19.5002C22.6684 19.9785 22.5045 20.5901 22.0262 20.8662C21.8742 20.954 21.7017 21.0002 21.5262 21.0002H2.47363C1.92135 21.0002 1.47363 20.5525 1.47363 20.0002C1.47363 19.8246 1.51984 19.6522 1.60761 19.5002L11.1339 3.00017C11.41 2.52187 12.0216 2.358 12.4999 2.63414C12.6519 2.72191 12.7782 2.84815 12.8659 3.00017ZM10.9999 16.0002V18.0002H12.9999V16.0002H10.9999ZM10.9999 9.00017V14.0002H12.9999V9.00017H10.9999Z"></path></svg>'),cc=xa('<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M15 7C15 6.44772 15.4477 6 16 6C16.5523 6 17 6.44772 17 7V17C17 17.5523 16.5523 18 16 18C15.4477 18 15 17.5523 15 17V7ZM7 7C7 6.44772 7.44772 6 8 6C8.55228 6 9 6.44772 9 7V17C9 17.5523 8.55228 18 8 18C7.44772 18 7 17.5523 7 17V7Z"></path></svg>'),uc=xa('<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M4 12H7C8.10457 12 9 12.8954 9 14V19C9 20.1046 8.10457 21 7 21H4C2.89543 21 2 20.1046 2 19V12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12V19C22 20.1046 21.1046 21 20 21H17C15.8954 21 15 20.1046 15 19V14C15 12.8954 15.8954 12 17 12H20C20 7.58172 16.4183 4 12 4C7.58172 4 4 7.58172 4 12Z"></path></svg>'),fc=oe('<button type="button" class="altcha-button altcha-button-secondary"><!></button>'),hc=oe('<audio hidden="" autoplay=""></audio>'),dc=oe('<div class="altcha-code-challenge"><form data-code-challenge="true"><!> <div class="altcha-code-challenge-text"> </div> <img class="altcha-code-challenge-image" alt=""/> <div class="altcha-code-challenge-row"><input type="text" class="altcha-input" autocomplete="off" name="" required=""/> <!> <button type="button" class="altcha-button altcha-button-secondary"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2V4C16.4183 4 20 7.58172 20 12C20 16.4183 16.4183 20 12 20C7.58172 20 4 16.4183 4 12C4 9.25022 5.38734 6.82447 7.50024 5.38451L7.5 8H9.5V2L3.5 2V4L5.99918 3.99989C3.57075 5.82434 2 8.72873 2 12Z"></path></svg></button></div> <div class="altcha-code-challenge-buttons"><button type="submit" class="altcha-button"> </button> <button type="button" class="altcha-button altcha-button-secondary"> </button></div></form> <!></div>');function Io(e,t){$t(t,!0);let n=ae(t,"audioUrl"),r=ae(t,"codeChallenge"),a=ae(t,"config"),o=ae(t,"imageUrl"),s=ae(t,"onCancel"),l=ae(t,"onReload"),f=ae(t,"onSubmit"),u=ae(t,"strings"),d=H(void 0),m=H(void 0),g=H(void 0),p=H(!1),b=H(""),y=H(!1);Sa(()=>(a().disableAutoFocus||on().then(()=>{var x;(x=i(g))==null||x.focus()}),()=>{i(m)&&(i(m).pause(),E(m,void 0))}));function M(){E(d,Se.PAUSED,!0)}function P(x){E(d,Se.ERROR,!0)}function z(){E(d,Se.READY,!0)}function se(){E(d,Se.LOADING,!0)}function j(){E(d,Se.PLAYING,!0)}function Te(){E(d,Se.PAUSED,!0)}function Y(x){var G;x.code==="Space"?(x.preventDefault(),x.stopPropagation(),$e()):x.code==="Escape"&&(x.preventDefault(),x.stopPropagation(),(G=s())==null||G())}function $(x){var G;x.preventDefault(),x.stopPropagation(),(G=f())==null||G(i(b))}function te(x){x.play().catch(G=>{if(!(G instanceof DOMException&&G.name==="AbortError"))throw G})}function $e(){i(m)?i(d)===Se.LOADING||(i(m).paused?(n()&&i(m).src!==n()&&(i(m).src=n()),i(m).currentTime=0,te(i(m))):i(m).pause()):(E(y,!0),requestAnimationFrame(()=>{i(m)&&n()&&(i(m).src=n(),te(i(m)))}))}var D={get audioUrl(){return n()},set audioUrl(x){n(x),Q()},get codeChallenge(){return r()},set codeChallenge(x){r(x),Q()},get config(){return a()},set config(x){a(x),Q()},get imageUrl(){return o()},set imageUrl(x){o(x),Q()},get onCancel(){return s()},set onCancel(x){s(x),Q()},get onReload(){return l()},set onReload(x){l(x),Q()},get onSubmit(){return f()},set onSubmit(x){f(x),Q()},get strings(){return u()},set strings(x){u(x),Q()}},be=dc(),et=_e(be),He=_e(et);{var Gt=x=>{var G=oc(),vn=kn(G,!0);Ae(()=>Et(vn,u().verificationRequired)),B(x,G)};me(He,x=>{a().codeChallengeDisplay!=="standard"&&x(Gt)})}var Fe=re(He,2),ue=kn(Fe,!0),C=re(Fe,2),ye=re(C,2),ie=_e(ye);Ra(ie),ie.disabled=i(p),Bt(ie,x=>E(g,x),()=>i(g));var ft=re(ie,2);{var k=x=>{var G=fc(),vn=_e(G);{var ja=Ve=>{var At=sc();B(Ve,At)},hr=Ve=>{var At=lc();B(Ve,At)},Ba=Ve=>{var At=cc();B(Ve,At)},za=Ve=>{var At=uc();B(Ve,At)};me(vn,Ve=>{i(d)===Se.LOADING?Ve(ja):i(d)===Se.ERROR?Ve(hr,1):i(d)===Se.PLAYING?Ve(Ba,2):Ve(za,-1)})}de(G),Ae(()=>{K(G,"title",u().getAudioChallenge),G.disabled=i(d)===Se.LOADING||i(d)===Se.ERROR,K(G,"aria-label",i(d)===Se.LOADING?u().loading:u().getAudioChallenge)}),ge("click",G,()=>$e(),!0),B(x,G)};me(ft,x=>{r().audio&&x(k)})}var dn=re(ft,2);de(ye);var Br=re(ye,2),ht=_e(Br),Va=kn(ht,!0),Mn=re(ht,2),cr=kn(Mn,!0);de(Br),de(et);var ur=re(et,2);{var fr=x=>{var G=hc();Bt(G,vn=>E(m,vn),()=>i(m)),ge("error",G,P),ge("loadstart",G,se),ge("canplay",G,z),ge("pause",G,Te),ge("playing",G,j),ge("ended",G,M),B(x,G)};me(ur,x=>{i(y)&&x(fr)})}return de(be),Ae(()=>{Et(ue,u().enterCodeFromImage),K(C,"src",o()),K(ie,"minlength",r().length||1),K(ie,"maxlength",r().length),K(ie,"placeholder",u().enterCode),K(ie,"aria-label",i(d)===Se.LOADING?u().loading:i(d)===Se.PLAYING?"":u().enterCodeAria),K(ie,"aria-live",i(d)?"assertive":"polite"),K(ie,"aria-busy",i(d)===Se.LOADING),K(dn,"title",u().reload),K(dn,"aria-label",u().reload),K(ht,"aria-label",u().verify),Et(Va,u().verify),K(Mn,"aria-label",u().cancel),Et(cr,u().cancel)}),ge("submit",et,$,!0),Ir("keydown",ie,Y),Gl(ie,()=>i(b),x=>E(b,x)),ge("click",dn,()=>{var x;return(x=l())==null?void 0:x()},!0),ge("click",Mn,()=>{var x;return(x=s())==null?void 0:x()},!0),B(e,be),Rt(D)}Or(["keydown"]),zt(Io,{audioUrl:{},codeChallenge:{},config:{},imageUrl:{},onCancel:{},onReload:{},onSubmit:{},strings:{}},[],[],{mode:"open"});var vc=new Set(["$$slots","$$events","$$legacy","$$host","anchor","children","display","backdrop","onClickOutside","onClickOutsideDelay","onClose","placement","updateUISignal","variant"]),pc=oe('<div class="altcha-popover-backdrop" data-backdrop=""></div>'),gc=oe('<div class="altcha-popover-arrow"></div>'),mc=oe('<div role="button" class="altcha-popover-close">&times;</div>'),bc=oe('<!> <div><!> <!> <div class="altcha-popover-content"><!></div></div>',1);function Da(e,t){$t(t,!0);let n=ae(t,"anchor"),r=ae(t,"children"),a=ae(t,"display",7,"standard"),o=ae(t,"backdrop",7,!1),s=ae(t,"onClickOutside"),l=ae(t,"onClickOutsideDelay",7,600),f=ae(t,"onClose"),u=ae(t,"placement",7,"auto"),d=ae(t,"updateUISignal"),m=ae(t,"variant",7,"neutral"),g=Ur(t,vc),p=H(void 0),b=H(void 0),y=H(!1),M=H(0);De(()=>{u()!=="auto"&&E(y,u()==="top")}),De(()=>{d()&&Te()}),Sa(()=>{const C=a()==="bottomsheet"||a()==="overlay";return C&&(i(b)&&document.body.append(i(b)),i(p)&&document.body.append(i(p))),Te(),on().then(()=>{E(M,Date.now(),!0)}),()=>{C&&(i(b)&&document.body.removeChild(i(b)),i(p)&&document.body.removeChild(i(p)))}});function P(){var C;(C=f())==null||C()}function z(C){var ie,ft;const ye=C.target;!((ie=i(p))!=null&&ie.contains(ye))&&(!l()||i(M)+l()<Date.now())&&((ft=s())==null||ft())}function se(){Te()}function j(){Te()}function Te(){if(n()&&u()==="auto"&&i(p)){const C=n().getBoundingClientRect(),ie=document.documentElement.clientHeight-(C.top+C.height)<i(p).clientHeight;i(y)!==ie&&E(y,ie)}}var Y={get anchor(){return n()},set anchor(C){n(C),Q()},get children(){return r()},set children(C){r(C),Q()},get display(){return a()},set display(C="standard"){a(C),Q()},get backdrop(){return o()},set backdrop(C=!1){o(C),Q()},get onClickOutside(){return s()},set onClickOutside(C){s(C),Q()},get onClickOutsideDelay(){return l()},set onClickOutsideDelay(C=600){l(C),Q()},get onClose(){return f()},set onClose(C){f(C),Q()},get placement(){return u()},set placement(C="auto"){u(C),Q()},get updateUISignal(){return d()},set updateUISignal(C){d(C),Q()},get variant(){return m()},set variant(C="neutral"){m(C),Q()}},$=bc();ge("click",nn,z,!0),ge("resize",nn,se),ge("scroll",nn,j);var te=_n($);{var $e=C=>{var ye=pc();Bt(ye,ie=>E(b,ie),()=>i(b)),B(C,ye)};me(te,C=>{o()&&C($e)})}var D=re(te,2);Dr(D,()=>({...g,class:`altcha-popover ${(t.class||"")??""}`,"data-popover":!0,"data-variant":m(),"data-top":i(y),"data-display":a()}));var be=_e(D);{var et=C=>{var ye=gc();B(C,ye)};me(be,C=>{a()==="standard"&&C(et)})}var He=re(be,2);{var Gt=C=>{var ye=mc();ge("click",ye,P,!0),B(C,ye)};me(He,C=>{a()!=="standard"&&C(Gt)})}var Fe=re(He,2),ue=_e(Fe);return $l(ue,()=>r()??Ht),de(Fe),de(D),Bt(D,C=>E(p,C),()=>i(p)),B(e,$),Rt(Y)}zt(Da,{anchor:{},children:{},display:{},backdrop:{},onClickOutside:{},onClickOutsideDelay:{},onClose:{},placement:{},updateUISignal:{},variant:{}},[],[],{mode:"open"});class Oo extends Error{constructor(){super(...arguments);S(this,"name","RequestError")}}function yc(e){return Array.from(new Uint8Array(e)).map(t=>t.toString(16).padStart(2,"0")).join("")}function wc(e,t="altcha-css",n){var r,a;if(typeof document<"u"&&document&&!document.getElementById(t)){const o=document.createElement("style");o.id=t,o.textContent=e;const s=((r=document.currentScript)==null?void 0:r.nonce)??((a=document.querySelector('meta[name="csp-nonce"]'))==null?void 0:a.content);s&&(o.nonce=s),document.head.appendChild(o)}}Object.values(Po);async function Lo(e){var g;const{challenge:t,concurrency:n=navigator.hardwareConcurrency,controller:r=new AbortController,createWorker:a,onOutOfMemory:o=p=>p>1?Math.floor(p/2):0,counterMode:s,timeout:l}=e,f=Math.min(16,Math.max(1,n)),u=[],d=()=>{for(const p of u)p.terminate()};for(let p=0;p<f;p++)u.push(await a(t.parameters.algorithm));let m=null;try{m=await Promise.race(u.map((p,b)=>(r.signal.addEventListener("abort",()=>{p.postMessage({type:"abort"})}),new Promise((y,M)=>{p.addEventListener("error",P=>{M(P)}),p.addEventListener("message",P=>{if(P.data){for(const z of u)z!==p&&z.postMessage({type:"abort"});if(P.data.error)return M(new Error(P.data.error))}y(P.data)}),p.postMessage({challenge:t,counterMode:s,counterStart:b,counterStep:f,timeout:l,type:"work"})}))))}catch(p){if(p instanceof Error&&!!((g=p==null?void 0:p.message)!=null&&g.includes("Out of memory"))&&o){d();const y=o(f);if(y)return Lo({...e,challenge:t,controller:r,concurrency:y,createWorker:a})}throw p}finally{d()}return r.signal.aborted?null:m||null}class _c{constructor(t={}){S(this,"TAG_CODES",{INPUT:1,TEXTAREA:2,SELECT:3,BUTTON:4,A:5,DETAILS:6,SUMMARY:7,IFRAME:8,VIDEO:9,AUDIO:10});S(this,"POINTER_TYPES",{mouse:0,pen:1,touch:2});S(this,"maxSamples");S(this,"sampleInterval");S(this,"target");S(this,"focusStartTime",0);S(this,"focusInteraction",0);S(this,"focusInteractionTimer");S(this,"lastPointerSample",0);S(this,"lastTouchSample",0);S(this,"lastScrollSample",0);S(this,"pendingClick",null);S(this,"keysDown",new Map);S(this,"moveTimes",[]);S(this,"untrusted",0);S(this,"click",[]);S(this,"focus",[]);S(this,"key",[]);S(this,"pointer",[]);S(this,"scroll",[]);S(this,"touch",[]);S(this,"markTime",null);S(this,"markUntrusted",0);S(this,"onFocus",t=>{if(!t.isTrusted){this.untrusted++;return}if(this.focusInteraction===2)return;const n=t.target;if(!(n instanceof Element))return;const r=performance.now();this.focusStartTime===0&&(this.focusStartTime=r),this.focus.push([Math.round(r-this.focusStartTime),n.tabIndex,this.TAG_CODES[n.tagName]??0,this.focusInteraction?1:0]),this.evict(this.focus)});S(this,"onKeyDown",t=>{if(!t.isTrusted){this.untrusted++;return}if(this.setInteraction(1),t.repeat)return;const n=t.timeStamp||performance.now(),r=[Math.round(n),-1];this.key.push(r),this.evict(this.key),this.keysDown.set(t.code,{sample:r,down:n})});S(this,"onKeyUp",t=>{if(!t.isTrusted){this.untrusted++;return}const n=this.keysDown.get(t.code);if(n){const r=t.timeStamp||performance.now();n.sample[1]=Math.round(r-n.down),this.keysDown.delete(t.code)}});S(this,"onPointerDown",t=>{if(!t.isTrusted){this.untrusted++;return}this.setInteraction(2);const n=t.timeStamp||performance.now(),r=t.target instanceof Element?t.target.getBoundingClientRect():null;let a=0;for(const s of this.moveTimes)s>=n-500&&s<n&&a++;const o=[Math.round(t.clientX),Math.round(t.clientY),Math.round(n),-1,r?Math.round(t.clientX-(r.left+r.width/2)):0,r?Math.round(t.clientY-(r.top+r.height/2)):0,this.POINTER_TYPES[t.pointerType]??3,a];this.click.push(o),this.evict(this.click),this.pendingClick={sample:o,down:n}});S(this,"onPointerUp",t=>{if(!t.isTrusted){this.untrusted++;return}if(this.pendingClick){const n=t.timeStamp||performance.now();this.pendingClick.sample[3]=Math.round(n-this.pendingClick.down),this.pendingClick=null}});S(this,"onPointer",t=>{if(!t.isTrusted){this.untrusted++;return}if(t.pointerType==="touch")return;const n=t.timeStamp||performance.now();this.moveTimes.push(n),this.moveTimes.length>32&&this.moveTimes.shift(),n-this.lastPointerSample>=this.sampleInterval&&(this.pointer.push([Math.round(t.clientX),Math.round(t.clientY),Math.round(n)]),this.lastPointerSample=n,this.evict(this.pointer))});S(this,"onScroll",t=>{if(!t.isTrusted){this.untrusted++;return}const n=performance.now();n-this.lastScrollSample<this.sampleInterval||(this.scroll.push([Math.round(window.scrollY),Math.round(n)]),this.lastScrollSample=n,this.evict(this.scroll))});S(this,"onTouchMove",t=>{if(!t.isTrusted){this.untrusted++;return}const n=t.timeStamp||performance.now(),r=t.touches[0];r&&n-this.lastTouchSample>=this.sampleInterval&&(this.touch.push([Math.round(r.clientX),Math.round(r.clientY),Math.round(n),Math.round(r.force*1e3)/1e3,Math.round(r.radiusX||0),Math.round(r.radiusY||0)]),this.lastTouchSample=n,this.evict(this.touch))});const{maxSamples:n=60,sampleInterval:r=50,target:a=window}=t;this.maxSamples=n,this.sampleInterval=r,this.target=a,this.attach()}destroy(){const t={capture:!0};this.target.removeEventListener("focusin",this.onFocus,t),this.target.removeEventListener("keydown",this.onKeyDown,t),this.target.removeEventListener("keyup",this.onKeyUp,t),this.target.removeEventListener("pointerdown",this.onPointerDown,t),this.target.removeEventListener("pointerup",this.onPointerUp,t),this.target.removeEventListener("pointermove",this.onPointer,t),this.target.removeEventListener("scroll",this.onScroll,t),this.target.removeEventListener("touchmove",this.onTouchMove,t)}export(){return{click:this.click,focus:this.focus,key:this.key,maxTouchPoints:navigator.maxTouchPoints||0,pointer:this.pointer,scroll:this.scroll,time:Date.now(),touch:this.touch,untrusted:this.untrusted,v:2,webdriver:navigator.webdriver===!0}}mark(){this.markTime=performance.now(),this.markUntrusted=this.untrusted}exportSinceMark(){var o;const t=this.export(),n=this.markTime;if(n===null)return t;const r=this.focus.filter(s=>this.focusStartTime+s[0]>=n),a=((o=r[0])==null?void 0:o[0])??0;return{...t,click:this.click.filter(s=>s[2]>=n),focus:r.map(s=>[s[0]-a,s[1],s[2],s[3]]),key:this.key.filter(s=>s[0]>=n),pointer:this.pointer.filter(s=>s[2]>=n),scroll:this.scroll.filter(s=>s[1]>=n),touch:this.touch.filter(s=>s[2]>=n),untrusted:this.untrusted-this.markUntrusted}}attach(){const t={passive:!0,capture:!0};this.target.addEventListener("focusin",this.onFocus,t),this.target.addEventListener("keydown",this.onKeyDown,t),this.target.addEventListener("keyup",this.onKeyUp,t),this.target.addEventListener("pointerdown",this.onPointerDown,t),this.target.addEventListener("pointerup",this.onPointerUp,t),this.target.addEventListener("pointermove",this.onPointer,t),this.target.addEventListener("scroll",this.onScroll,t),this.target.addEventListener("touchmove",this.onTouchMove,t)}evict(t){t.length>this.maxSamples&&t.splice(0,t.length-this.maxSamples)}setInteraction(t){this.focusInteraction=t,clearTimeout(this.focusInteractionTimer),this.focusInteractionTimer=setTimeout(()=>{this.focusInteraction=0},100)}}var kc=oe('<div class="altcha-overlay-backdrop" data-backdrop=""></div>'),xc=oe('<div class="altcha-overlay-content"></div>'),Ec=oe('<div role="button" class="altcha-overlay-close">&times;</div> <!>',1),Sc=oe('<div class="altcha-floating-arrow"></div>'),Cc=oe('<input type="hidden"/>'),Ac=oe('<div class="altcha-error">Secure context (HTTPS) required.</div>'),Mo=oe('<div class="altcha-error"> </div>'),Tc=oe("<!> <!>",1),$c=oe('<!> <div class="altcha"><!> <div class="altcha-main"><div><div class="altcha-checkbox-wrap"><!> <label><!></label></div> <!></div> <!> <!> <!></div> <!></div>',1);function Rc(e,t){$t(t,!0);const n=()=>Ci(d,"$altchaDefaults",a),r=()=>Ci(b,"$altchaI18nStore",a),[a,o]=qs(),s='input[type="text"]:not([data-no-spamfilter]), textarea:not([data-no-spamfilter])',l='input[type="submit"], button[type="submit"], button:not([type="button"]):not([type="reset"])',f=["ar","fa","he","ur"],{isSecureContext:u}=globalThis,{store:d}=globalThis.$altcha.defaults,m=navigator.hardwareConcurrency||2,g=navigator.deviceMemory||0,p=g&&g<=4?Math.min(4,m):m,b=globalThis.$altcha.i18n.store,y=t.$$host,M=(c,v)=>{on().then(()=>{y==null||y.dispatchEvent(new CustomEvent(c,{detail:v}))})};let P=null,z=H(Pt(new URL(location.origin))),se=H(!1),j=H(null),Te=H(null),Y=H(null),$=H(Pt(X.UNVERIFIED)),te=H(void 0),$e=H(void 0),D=H(null),be=H(void 0),et=H(null),He=H(null),Gt=H(null),Fe=H(null),ue=H(null),C=H(Pt([])),ye=H(0),ie=H(Pt({})),ft=H(!0);const k=Pe(()=>({fetch:(c,v)=>fetch(c,v),audioChallengeLanguage:"",auto:"off",barPlacement:"bottom",challenge:"",codeChallenge:null,codeChallengeDisplay:"standard",credentials:null,debug:!1,disableAutoFocus:!1,display:"standard",floatingAnchor:"",floatingOffset:8,floatingPersist:!1,floatingPlacement:"auto",formatError:null,hideFooter:!1,hideLogo:!1,humanInteractionSignature:!0,language:"",mockError:!1,minDuration:500,overlayContent:"",name:"altcha",popoverPlacement:"auto",retryOnOutOfMemoryError:!0,setCookie:null,serverVerificationFields:!1,serverVerificationTimeZone:!1,test:!1,timeout:9e4,type:"checkbox",validationMessage:"",verifyFunction:null,verifyUrl:"",workers:p,...n(),...i(ie)})),dn=Pe(()=>`altcha-checkbox-${t.id||Math.floor(Math.random()*1e12).toString(16)}`),Br=Pe(()=>Oc(i(k).type)),ht=Pe(()=>i(k).auto),Va=Pe(()=>i($)===X.VERIFYING),Mn=Pe(()=>!i(k).hideFooter),cr=Pe(()=>!i(k).hideLogo&&i(k).display!=="bar"),ur=Pe(()=>Lc(r(),[i(k).language,document.documentElement.lang,...navigator.languages])),fr=Pe(()=>f.includes(i(ur).language)?"rtl":void 0),x=Pe(()=>({...i(ur).strings})),G=Pe(()=>{var c,v,_;return(v=(c=i(j))==null?void 0:c.audio)!=null&&v.match(/^(https?:)?\//)?zr(i(j).audio,i(z),{language:i(k).audioChallengeLanguage||i(ur).language}).toString():(_=i(j))==null?void 0:_.audio}),vn=Pe(()=>{var c,v,_;return(v=(c=i(j))==null?void 0:c.image)!=null&&v.match(/^(https?:)?\//)?zr(i(j).image,i(z)):(_=i(j))==null?void 0:_.image});De(()=>{dr({auto:t.auto,challenge:t.challenge,display:t.display,language:t.language,name:t.name,type:t.type,workers:t.workers})}),De(()=>{t.theme?y==null||y.setAttribute("theme",t.theme):y==null||y.removeAttribute("theme")}),De(()=>{if(t.configuration)try{dr(JSON.parse(t.configuration))}catch{ee("unable to parse the `configuration` attribute (JSON expected)")}}),De(()=>{i(Y)!==i(k).display&&Kr(i(k).display)}),De(()=>{i(se)&&i($)===X.VERIFYING&&E(se,!1)}),De(()=>{!i(se)&&i($)===X.VERIFIED&&E(se,!0)}),De(()=>{if(!i(se)){const c=Ka();c&&c.checked&&(c.checked=!1)}}),De(()=>{var c;i($)===X.VERIFIED&&((c=Ka())==null||c.setCustomValidity(""))}),De(()=>{if(i(ht)==="onload"){const c=setTimeout(()=>{Nn()},10);return()=>{c&&clearTimeout(c)}}}),De(()=>{i(He)&&ee("error:",i(He))}),De(()=>{i(ue)&&i(k).setCookie&&Wc(i(ue),i(k).setCookie)}),Sa(()=>{var c,v;return ee("mounted","3.6.0"),y&&globalThis.$altcha.instances.add(y),E(D,Nc(),!0),(c=i(D))==null||c.addEventListener("reset",Yo),(v=i(D))==null||v.addEventListener("submit",Go,{capture:!0}),document.addEventListener("focusin",Ko),ja(),i(k).humanInteractionSignature&&(ee("human interaction signature enabled"),P=new _c),M("load"),u||ee("secure context (HTTPS) required"),()=>{var _,w;Ba(),y&&globalThis.$altcha.instances.delete(y),i(Fe)&&clearTimeout(i(Fe)),(_=i(D))==null||_.removeEventListener("reset",Yo),(w=i(D))==null||w.removeEventListener("submit",Go,{capture:!0}),document.removeEventListener("focusin",Ko),P==null||P.destroy()}});function ja(){E(C,[...globalThis.$altcha.plugins].map(c=>new c(y)),!0),ee("activating plugins",i(C).map(c=>c.constructor.name));for(const c of i(C))c.activate()}async function hr(c,...v){let _;for(const w of i(C))_=await w[c].call(w,...v);return _}function Ba(){for(const c of i(C))c.destroy()}function za(c){const[v,_]=c.salt.split("?"),w={};if(_)try{Object.assign(w,Object.fromEntries(new URLSearchParams(_).entries()))}catch{}const V={codeChallenge:c.codeChallenge,parameters:{algorithm:c.algorithm,cost:1,data:w,expiresAt:w!=null&&w.expires?parseInt(w.expires,10):void 0,keyLength:c.algorithm==="SHA-512"?64:c.algorithm==="SHA-384"?48:32,nonce:yc(new TextEncoder().encode(c.salt)),keyPrefix:c.challenge,salt:""},signature:c.signature};return Object.defineProperties(V,{_originalSalt:{enumerable:!1,value:c.salt,writable:!1},_version:{enumerable:!1,value:1,writable:!1}}),V}function Ve(c,v){return{algorithm:c.parameters.algorithm,challenge:c.parameters.keyPrefix,number:v.counter,salt:"_originalSalt"in c?c._originalSalt:c.parameters.nonce,signature:c.signature,took:v.time||0}}async function At(c){await new Promise(v=>setTimeout(v,c))}async function Bo(c=i(k).challenge,v){const _=await hr("onFetchChallenge",c);let w=null;if(_!==void 0)return _;if(typeof c=="string")if(c.startsWith("{")){ee("parsing JSON challenge");try{w=JSON.parse(c)}catch{throw new Error("Unable to parse JSON challenge.")}}else{ee("fetching challenge from",(v==null?void 0:v.method)||"GET",c),E(z,new URL(c,location.origin),!0);const{headers:V,json:A}=await Wo(c,{credentials:i(k).credentials||void 0,...v}),le=V.get("x-altcha-config");if(le&&Yc(le),A&&"his"in A&&A.his){if(ee("requested HIS"),!P)throw new Error("Server requested HIS data but collector is disabled.");const ke=P.export();return P.mark(),Bo(zr(A.his.url,i(z)),{body:JSON.stringify({his:ke}),headers:{"content-type":"application/json"},method:"POST"})}A&&"hisResult"in A&&A.hisResult&&ee("HIS result",A.hisResult),w=A}else if(c&&typeof c=="object")try{w=JSON.parse(JSON.stringify(c))}catch{throw new Error("Unable to parse JSON challenge.")}if(Pc(w)&&(w=za(w)),!Ic(w))throw new Error("Challenge validation failed.");return w}function Pc(c){return c!==null&&typeof c=="object"&&"challenge"in c}function Ic(c){return!!c&&typeof c=="object"&&"parameters"in c&&!!c.parameters&&typeof c.parameters=="object"&&"algorithm"in c.parameters&&"nonce"in c.parameters&&"salt"in c.parameters&&"keyPrefix"in c.parameters}function Ka(){return document.getElementById(i(dn))}function Oc(c){switch(c){case"checkbox":return To;case"switch":return Ro;case"native":default:return $o}}function Lc(c,v){const _=Object.keys(c).map(V=>V.toLowerCase());let w=v.reduce((V,A)=>(A=A.toLowerCase(),V||(c[A]?A:null)||_.find(le=>A.split("-")[0]===le.split("-")[0])||null),null);return c[w||""]||(w="en"),{language:w,strings:c[w]}}function Mc(c){switch(c){case"bar":return i(k).barPlacement||"bottom";case"floating":return i(k).floatingPlacement||"auto";default:return}}function Nc(){var c,v;if(t.form){const _=y==null?void 0:y.getRootNode(),w=(c=_==null?void 0:_.getElementById)==null?void 0:c.call(_,t.form);return w instanceof HTMLFormElement?w:null}return((v=i(be))==null?void 0:v.closest("form"))??null}function zo(c){return i(D)?Array.from(i(D).elements).filter(v=>v.matches(c)):[]}function Dc(c){return zo(s).reduce((_,w)=>{const V=w.name,A=w.value;return V&&A&&(_[V]=/\n/.test(A)?A.replace(new RegExp("(?<!\\r)\\n","g"),`\r
`):A),_},{})}function Uc(){try{return Intl.DateTimeFormat().resolvedOptions().timeZone}catch{}}function zr(c,v,_){const w=new URL(c,v);if(w.search||(w.search=v.search),_)for(const V in _)_[V]!==void 0&&_[V]!==null&&w.searchParams.set(V,_[V]);return w.toString()}function Hc(c){!i(se)&&c.currentTarget.checked?(c.preventDefault(),c.currentTarget.checked=!1,i($)!==X.VERIFYING&&Nn()):c.currentTarget.checked||(c.preventDefault(),tt())}function Fc(c){i($)===X.VERIFYING?c.currentTarget.setCustomValidity(i(x).waitAlert):i(k).validationMessage&&c.currentTarget.setCustomValidity(i(k).validationMessage)}function Vc(){Kr(i(k).display),tt()}function jc(){Yr()}function Bc(c){const v=c.target;i(k).display==="floating"&&v&&!(y!=null&&y.contains(v))&&!v.hasAttribute("data-backdrop")&&!v.closest("[data-popover]")&&i($)!==X.VERIFIED&&!i(k).floatingPersist&&Ya()}function Ko(c){const v=c.target;i(ht)==="onfocus"&&i($)===X.UNVERIFIED&&i(D)&&v instanceof Element&&(i(D).contains(v)||v.form===i(D))&&Nn()}function Yo(){Kr(i(k).display),tt()}function Go(c){const v=c.target;(v==null?void 0:v.getAttribute("data-code-challenge"))!=="true"&&i(ht)==="onsubmit"&&i($)===X.UNVERIFIED&&(c.preventDefault(),c.stopPropagation(),E(et,c.submitter,!0),Ga(),Nn().then(_=>{_&&!i(j)&&on().then(()=>{qo(i(et))})}))}function zc(c){c.persisted&&(Kr(i(k).display),tt())}function Kc(){Yr()}function Yc(c){var v,_;try{const w=JSON.parse(c);w&&typeof w=="object"&&dr({serverVerificationFields:(v=w==null?void 0:w.sentinel)==null?void 0:v.fields,serverVerificationTimeZone:(_=w==null?void 0:w.sentinel)==null?void 0:_.timeZone,verifyUrl:w.verifyurl,...w})}catch(w){ee("unable to configure from x-altcha-config header",w)}}function Gc(c=20){if(!i(be))return;const v=i(k).floatingPlacement;if(!i($e)&&(E($e,(i(k).floatingAnchor instanceof HTMLElement?i(k).floatingAnchor:i(k).floatingAnchor?document.querySelector(i(k).floatingAnchor):zo(l)[0])||i(D),!0),!i($e))){ee("unable to find floating anchor element");return}const _=parseInt(i(k).floatingOffset,10)||12,w=i($e).getBoundingClientRect(),V=i(be).getBoundingClientRect(),A=document.documentElement.clientHeight,le=document.documentElement.clientWidth,ke=!v||v==="auto"?w.bottom+V.height+_+c>A:v==="top",ne=Math.max(c,Math.min(le-c-V.width,w.left+w.width/2-V.width/2));if(i(be).style.setProperty("--altcha-floating-left",`${ne}px`),i(be).style.setProperty("--altcha-floating-top",ke?`${w.top-(V.height+_)}px`:`${w.bottom+_}px`),i(be).setAttribute("data-floating-position",ke?"top":"bottom"),i(te)){const Le=i(te).getBoundingClientRect();i(te).style.left=w.left-ne+w.width/2-Le.width/2+"px"}}async function qc(c,v){var V;const _=await hr("onRequestServerVerification",c,v);if(_!==void 0)return _;if(ee("requesting server verification from",i(k).verifyUrl),!i(k).verifyUrl)throw new Error("Parameter verifyUrl must be set for server verification.");const{json:w}=await Wo(zr(i(k).verifyUrl,i(z)),{body:JSON.stringify({code:v,fields:i(k).serverVerificationFields?Dc():void 0,his:(V=i(j))!=null&&V.his&&P?P.exportSinceMark():void 0,payload:c,timeZone:i(k).serverVerificationTimeZone?Uc():void 0}),credentials:i(k).credentials||void 0,headers:{"Content-Type":"application/json"},method:"POST"});return w&&typeof w=="object"&&"payload"in w&&w.payload&&M("serververification",w),w}function qo(c){var v;i(D)&&"requestSubmit"in i(D)?i(D).requestSubmit(c):(v=i(D))!=null&&v.reportValidity()&&(c?c.click():i(D).submit())}function Wc(c,v={}){const{domain:_,name:w=i(k).name,maxAge:V,path:A,sameSite:le,secure:ke}=v;let ne=`${encodeURIComponent(w)}=${encodeURIComponent(c)}`;_&&(ne+=`; Domain=${_}`),V!=null&&(ne+=`; Max-Age=${V}`),A&&(ne+=`; Path=${A}`),le&&(ne+=`; SameSite=${le}`),ke&&(ne+="; Secure"),document.cookie=ne}function Kr(c){switch(c){case"bar":case"floating":case"overlay":Ya(),(!i(ht)||i(ht)==="off")&&(i(ie).auto="onsubmit");break;case"standard":Ga()}i(Y)!==c&&E(Y,c,!0)}function Xc(c){i(Fe)&&clearTimeout(i(Fe));const v=()=>{i($)!==X.UNVERIFIED?(E(se,!1),Ut(X.EXPIRED)):tt(),M("expired")},_=c*1e3-Date.now();_>=1?E(Fe,setTimeout(v,_),!0):v()}async function Wo(c,v){try{const _=await i(k).fetch(c,v);return await Jc(_),{headers:_.headers,json:await _.json()}}catch(_){throw new Oo(_ instanceof Error?_.message:String(_))}}async function Jc(c){var _;if(c.status>=400){if((_=c.headers.get("content-type"))!=null&&_.includes("/json")){let w;try{w=await c.json()}catch{}if(w&&"error"in w)throw new Error(`Server responded with ${c.status} - ${w.error}`)}throw new Error(`Server responded with ${c.status}.`)}const v=c.headers.get("content-type");if(!v||!v.includes("/json"))throw new Error(`Server responded with invalid content-type. Expected application/json, received ${v}.`)}async function Xo(c){var _;if(!i(ue)){vr(new Error("Cannot verify code challenge without PoW payload."));return}Ut(X.VERIFYING);let v=null;if(i(k).verifyUrl)v=await qc(i(ue),c);else if(i(k).verifyFunction)v=await i(k).verifyFunction(i(ue),c);else{vr(new Error("Parameter verifyUrl is required for code challenge verification."));return}v!=null&&v.payload&&(E(ue,v.payload,!0),ee("server payload",i(ue))),(v==null?void 0:v.verified)===!0?(ee("verified"),Ut(X.VERIFIED),M("verified",{payload:i(ue)}),i(ht)==="onsubmit"&&on().then(()=>{qo(i(et))})):vr(new Error((v==null?void 0:v.reason)||"Verification failed.")),i(k).disableAutoFocus||(_=Ka())==null||_.focus()}function dr(c){Object.assign(i(ie),{...Object.fromEntries(Object.entries(c).filter(([v,_])=>_!==void 0))})}function Zc(){return{...i(k)}}function Qc(){return i($)}function Ya(){E(ft,!1)}function ee(...c){(i(k).debug||c.some(v=>v instanceof Error))&&console[c[0]instanceof Error?"error":"log"]("ALTCHA",`[name=${i(k).name}]`,...c)}function tt(c=X.UNVERIFIED,v=null){E(se,!1),E(He,v,!0),E(ue,null),i(Te)&&i(Te).abort(),i(Fe)&&(clearTimeout(i(Fe)),E(Fe,null)),Ut(c)}function vr(c){const v=c instanceof Error?c:new Error(String(c));Ut(X.ERROR,String(v)),i(k).formatError?E(Gt,i(k).formatError(v)||null,!0):v instanceof Oo&&E(Gt,`Error: ${v.message}`)}function Ut(c,v=null){E($,c,!0),E(He,v,!0),E(Gt,null),M("statechange",{payload:i(ue),state:i($)})}function Ga(){E(ft,!0),on().then(()=>{Yr()})}function Yr(){switch(i(k).display){case"floating":return Gc()}E(ye,i(ye)+1)}async function Nn(c={}){var Le,qt;const{concurrency:v=Math.max(1,i(k).workers),controller:_=new AbortController,minDuration:w=i(k).minDuration}=c,V=performance.now();let A=null,le=null,ke=!1;const ne=await hr("onVerify",c);if(ne!==void 0)return ne;tt(X.VERIFYING),E(Te,_,!0);try{if(!u)throw new Error("Secure context (HTTPS) required.");if(i(k).mockError)throw new Error("Mock error.");if(i(k).test)return ee("running test mode with null challenge"),await At(Math.max(0,w-(performance.now()-V))),(Le=i(Te))!=null&&Le.signal.aborted?(tt(),null):(E(ue,btoa(JSON.stringify({challenge:null,solution:null,test:!0})),!0),ee("verified"),Ut(X.VERIFIED),M("verified",{payload:i(ue)}),{payload:i(ue)});if(A=await Bo(),!A)throw new Error("Failed to fetch challenge.");ee("challenge",A),"configuration"in A&&(ee("re-configuring from challenge",A.configuration),dr(A.configuration)),A.parameters.expiresAt&&Xc(A.parameters.expiresAt),ke="_version"in A&&A._version===1;const dt=globalThis.$altcha.algorithms.get(A.parameters.algorithm);if(!dt)throw new Error(`Unsupported algorithm ${A.parameters.algorithm}.`);if(le=await Lo({challenge:A,concurrency:v,controller:_,createWorker:dt,counterMode:ke?"string":"uint32",onOutOfMemory:rs=>{if(ee("out of memory error received"),M("outofmemory"),i(k).retryOnOutOfMemoryError&&rs>1){const as=Math.floor(rs/2);return ee(`retrying with ${as} workers...`),as}},timeout:i(k).timeout}),(qt=i(Te))!=null&&qt.signal.aborted)return tt(),null;if(!le)throw new Error("Failed to find solution.");ee("solution",le),await At(Math.max(0,w-(performance.now()-V))),E(j,A.codeChallenge||i(k).codeChallenge||null,!0),ke?E(ue,btoa(JSON.stringify(Ve(A,le))),!0):E(ue,btoa(JSON.stringify({challenge:{parameters:A.parameters,signature:A.signature},solution:le})),!0),i(j)?(ee("requesting code verification"),Ut(X.CODE),M("codechallenge",{codeChallenge:i(j)})):i(k).verifyUrl?await Xo():(ee("verified"),Ut(X.VERIFIED),M("verified",{payload:i(ue)}))}catch(dt){return ee("verification failed",dt),vr(dt),null}finally{E(Te,null)}return{challenge:A,payload:i(ue),solution:le}}var eu={configure:dr,getConfiguration:Zc,getState:Qc,hide:Ya,log:ee,reset:tt,setState:Ut,show:Ga,updateUI:Yr,verify:Nn},Jo=$c();ge("scroll",ra,jc),ge("click",ra,Bc),ge("pageshow",nn,zc),ge("resize",nn,Kc);var Zo=_n(Jo);{var tu=c=>{var v=kc();B(c,v)};me(Zo,c=>{i(k).display==="overlay"&&i(ft)&&c(tu)})}var Tt=re(Zo,2),Qo=_e(Tt);{var nu=c=>{var v=Ec(),_=_n(v),w=re(_,2);{var V=A=>{var le=xc();vo(le,()=>{var ke;return(ke=document.querySelector(i(k).overlayContent))==null?void 0:ke.innerHTML},!0),de(le),B(A,le)};me(w,A=>{i(k).overlayContent&&A(V)})}ge("click",_,Vc,!0),B(c,v)};me(Qo,c=>{i(k).display==="overlay"&&i(ft)&&c(nu)})}var qa=re(Qo,2),Wa=_e(qa),Xa=_e(Wa),es=_e(Xa);{let c=Pe(()=>i(k).display==="standard"&&i(ht)!=="onsubmit"||i($)===X.VERIFYING);Il(es,()=>i(Br),(v,_)=>{_(v,{get id(){return i(dn)},get form(){return t.form},name:"",get required(){return i(c)},get loading(){return i(Va)},get checked(){return i(se)},onchange:Hc,oninvalid:Fc})})}var Ja=re(es,2),ru=_e(Ja);{var au=c=>{var v=Lr();Ae(()=>Et(v,i(x).verificationRequired)),B(c,v)},iu=c=>{var v=Lr();Ae(()=>Et(v,i(x).verifying)),B(c,v)},ou=c=>{var v=Lr();Ae(()=>Et(v,i(x).verified)),B(c,v)},su=c=>{var v=Lr();Ae(()=>Et(v,i(x).label)),B(c,v)};me(ru,c=>{i($)===X.CODE&&i(j)?c(au):i($)===X.VERIFYING?c(iu,1):i($)===X.VERIFIED?c(ou,2):c(su,-1)})}de(Ja),de(Xa);var lu=re(Xa,2);{var cu=c=>{Ma(c,{get strings(){return i(x)}})};me(lu,c=>{i(cr)&&c(cu)})}de(Wa);var ts=re(Wa,2);{var uu=c=>{{let v=Pe(()=>i(k).display==="bar"&&i(cr));Na(c,{get logo(){return i(v)},get strings(){return i(x)}})}};me(ts,c=>{i(Mn)&&c(uu)})}var ns=re(ts,2);{var fu=c=>{var v=Sc();Bt(v,_=>E(te,_),()=>i(te)),B(c,v)};me(ns,c=>{i(k).display==="floating"&&c(fu)})}var hu=re(ns,2);{var du=c=>{var v=Cc();Ra(v),Ae(()=>{K(v,"form",t.form),K(v,"name",i(k).name),Kl(v,i(ue))}),B(c,v)};me(hu,c=>{i(k).setCookie||c(du)})}de(qa);var vu=re(qa,2);{var pu=c=>{Da(c,{get anchor(){return i(be)},onClickOutside:()=>{u&&tt()},get placement(){return i(k).popoverPlacement},role:"alert",variant:"error",get dir(){return i(fr)},get updateUISignal(){return i(ye)},children:(v,_)=>{var w=uo(),V=_n(w);{var A=ne=>{var Le=Ac();B(ne,Le)},le=ne=>{var Le=Mo(),qt=kn(Le,!0);Ae(()=>Et(qt,i(x).expired)),B(ne,Le)},ke=ne=>{var Le=Mo(),qt=kn(Le,!0);Ae(()=>{K(Le,"title",i(He)),Et(qt,i(Gt)||i(x).error)}),B(ne,Le)};me(V,ne=>{!i(He)&&!u?ne(A):!i(He)&&i($)===X.EXPIRED?ne(le,1):ne(ke,-1)})}B(v,w)},$$slots:{default:!0}})},gu=c=>{var v=uo(),_=_n(v);Pl(_,()=>i(j),w=>{{let V=Pe(()=>i(k).codeChallengeDisplay!=="standard");Da(w,{get anchor(){return i(be)},get backdrop(){return i(V)},get display(){return i(k).codeChallengeDisplay},onClose:()=>{tt()},get placement(){return i(k).popoverPlacement},role:"dialog",get"aria-label"(){return i(x).verificationRequired},get dir(){return i(fr)},get updateUISignal(){return i(ye)},children:(A,le)=>{var ke=Tc(),ne=_n(ke);Io(ne,{get audioUrl(){return i(G)},get imageUrl(){return i(vn)},onCancel:()=>tt(),onReload:()=>Nn(),onSubmit:dt=>Xo(dt).catch(vr),get codeChallenge(){return i(j)},get config(){return i(k)},get strings(){return i(x)}});var Le=re(ne,2);{var qt=dt=>{Na(dt,{get logo(){return i(cr)},get strings(){return i(x)}})};me(Le,dt=>{i(Mn)&&i(k).codeChallengeDisplay!=="standard"&&dt(qt)})}B(A,ke)},$$slots:{default:!0}})}}),B(c,v)};me(vu,c=>{i(He)||i($)===X.EXPIRED||!u?c(pu):i(j)&&i($)===X.CODE&&c(gu,1)})}de(Tt),Bt(Tt,c=>E(be,c),()=>i(be)),Ae(c=>{K(Tt,"data-state",i($)),K(Tt,"data-display",i(k).display||void 0),K(Tt,"data-placement",c),K(Tt,"data-visible",i(ft)||void 0),K(Tt,"dir",i(fr)),K(Ja,"for",i(dn)),Tt.dir=Tt.dir},[()=>Mc(i(k).display)]),B(e,Jo);var mu=Rt(eu);return o(),mu}typeof window<"u"&&window.customElements&&!customElements.get("altcha-widget")&&customElements.define("altcha-widget",zt(Rc,{auto:{type:"String"},challenge:{type:"String"},configuration:{type:"String"},display:{type:"String"},form:{type:"String"},language:{type:"String"},name:{type:"String"},theme:{type:"String"},type:{type:"String"},workers:{type:"Number"}},[],["configure","getConfiguration","getState","hide","log","reset","setState","show","updateUI","verify"]));const No=`(function() {
  "use strict";
  function assertAlgorithm(algorithm, allowed) {
    if (!allowed.includes(algorithm)) {
      throw new Error(
        \`Unsupported algorithm: \${String(algorithm)}. Expected one of: \${allowed.join(", ")}.\`
      );
    }
  }
  function bufferStartsWith(buffer, prefix) {
    if (prefix.length > buffer.length) {
      return false;
    }
    for (let i = 0; i < prefix.length; i++) {
      if (buffer[i] !== prefix[i]) {
        return false;
      }
    }
    return true;
  }
  function bufferToHex(buffer) {
    return Array.from(new Uint8Array(buffer)).map((b) => b.toString(16).padStart(2, "0")).join("");
  }
  function concatBuffers(a, b) {
    const out = new Uint8Array(a.length + b.length);
    out.set(a, 0);
    out.set(b, a.length);
    return out;
  }
  function hexToBuffer(hex) {
    if (hex.length % 2 !== 0) {
      throw new Error(\`Hex string must have an even length. Got: \${hex}\`);
    }
    if (!/^[0-9a-fA-F]*$/.test(hex)) {
      throw new Error("Hex string contains non-hex characters.");
    }
    const buffer = new Uint8Array(hex.length / 2);
    for (let i = 0; i < buffer.length; i++) {
      buffer[i] = parseInt(hex.substring(i * 2, i * 2 + 2), 16);
    }
    return buffer;
  }
  async function delay(ms) {
    await new Promise((resolve) => setTimeout(resolve, ms));
  }
  function timeDuration(start) {
    return Math.floor((performance.now() - start) * 10) / 10;
  }
  var HmacAlgorithm = /* @__PURE__ */ ((HmacAlgorithm2) => {
    HmacAlgorithm2["SHA_256"] = "SHA-256";
    HmacAlgorithm2["SHA_384"] = "SHA-384";
    HmacAlgorithm2["SHA_512"] = "SHA-512";
    return HmacAlgorithm2;
  })(HmacAlgorithm || {});
  Object.values(HmacAlgorithm);
  const MAX_COUNTER = {
    string: Number.MAX_SAFE_INTEGER,
    uint32: 4294967295
  };
  function isValidCounter(n, mode) {
    return Number.isInteger(n) && n >= 0 && n <= MAX_COUNTER[mode];
  }
  class PasswordBuffer {
    constructor(nonce, mode = "uint32") {
      this.nonce = nonce;
      this.mode = mode;
      this.buffer = new Uint8Array(this.nonce.length + this.COUNTER_BYTES);
      this.buffer.set(this.nonce, 0);
      this.dataView = new DataView(this.buffer.buffer);
    }
    nonce;
    mode;
    COUNTER_BYTES = 4;
    buffer;
    dataView;
    encoder = new TextEncoder();
    /**
     * Appends the counter to the nonce buffer.
     * In 'string' mode, encodes the counter as a UTF-8 string.
     * In 'uint32' mode, writes the counter as a big-endian 32-bit integer.
     * Throws a RangeError unless the counter is an integer the mode encodes exactly.
     */
    setCounter(n) {
      if (!isValidCounter(n, this.mode)) {
        throw new RangeError(
          \`counter must be an integer from 0 to \${MAX_COUNTER[this.mode]}. Got: \${n}\`
        );
      }
      if (this.mode === "string") {
        return concatBuffers(this.nonce, this.encoder.encode(n.toString()));
      }
      this.dataView.setUint32(this.nonce.length, n, false);
      return this.buffer;
    }
  }
  function assertKeyPrefix(keyPrefix, keyLength) {
    if (typeof keyPrefix !== "string" || !/^[0-9a-fA-F]+$/.test(keyPrefix)) {
      throw new Error("keyPrefix must be a non-empty hex string.");
    }
    if (keyPrefix.length > keyLength * 2) {
      throw new Error(
        \`keyPrefix (\${keyPrefix.length} hex characters) must not be longer than the key (keyLength: \${keyLength} bytes).\`
      );
    }
  }
  async function solveChallenge(options) {
    const {
      challenge,
      controller,
      counterMode = "uint32",
      counterStart = 0,
      counterStep = 1,
      deriveKey: deriveKey2,
      timeout = 9e4
    } = options;
    const { nonce, keyLength = 32, keyPrefix, salt } = challenge.parameters;
    assertKeyPrefix(keyPrefix, keyLength);
    const nonceBuf = hexToBuffer(nonce);
    const saltBuf = hexToBuffer(salt);
    const keyPrefixHex = keyPrefix.toLowerCase();
    const keyPrefixBuf = keyPrefix.length % 2 === 0 ? hexToBuffer(keyPrefix) : null;
    const password = new PasswordBuffer(nonceBuf, counterMode);
    const start = performance.now();
    let counter = counterStart;
    let iterations = 0;
    let derivedKeyHex = "";
    let lastYield = start;
    while (true) {
      if (controller?.signal.aborted || timeout && iterations % 10 === 0 && performance.now() - start > timeout) {
        return null;
      }
      const { derivedKey } = await deriveKey2(
        challenge.parameters,
        saltBuf,
        password.setCounter(counter)
      );
      if (iterations % 10 === 0 && performance.now() - lastYield > 200) {
        await delay(0);
        lastYield = performance.now();
      }
      if (keyPrefixBuf ? bufferStartsWith(derivedKey, keyPrefixBuf) : bufferToHex(derivedKey).startsWith(keyPrefixHex)) {
        derivedKeyHex = bufferToHex(derivedKey);
        break;
      }
      counter = counter + counterStep;
      iterations = iterations + 1;
    }
    return {
      counter,
      derivedKey: derivedKeyHex,
      time: timeDuration(start)
    };
  }
  function handler(options) {
    const { deriveKey: deriveKey2 } = options;
    let controller = void 0;
    self.onmessage = async (message) => {
      const { challenge, counterMode, counterStart, counterStep, timeout, type } = message.data;
      if (type === "abort") {
        controller?.abort();
      } else if (type === "work") {
        controller = new AbortController();
        let solution;
        try {
          solution = await solveChallenge({
            challenge,
            controller,
            counterStart,
            counterStep,
            deriveKey: deriveKey2,
            counterMode,
            timeout
          });
        } catch (err) {
          return self.postMessage({ error: err });
        }
        self.postMessage(solution);
      }
    };
  }
  async function deriveKey(parameters, salt, password) {
    const { algorithm, cost, keyLength = 32 } = parameters;
    assertAlgorithm(algorithm, ["PBKDF2/SHA-256", "PBKDF2/SHA-384", "PBKDF2/SHA-512"]);
    const passwordKey = await crypto.subtle.importKey(
      "raw",
      password,
      { name: "PBKDF2" },
      false,
      ["deriveBits"]
    );
    const derivedBits = await crypto.subtle.deriveBits(
      {
        name: "PBKDF2",
        salt,
        iterations: cost,
        hash: algorithm.slice("PBKDF2/".length)
      },
      passwordKey,
      keyLength * 8
    );
    return {
      parameters: {},
      derivedKey: new Uint8Array(derivedBits)
    };
  }
  handler({
    deriveKey
  });
})();
`,Do=typeof self<"u"&&self.Blob&&new Blob(["(self.URL || self.webkitURL).revokeObjectURL(self.location.href);",No],{type:"text/javascript;charset=utf-8"});function Ua(e){let t;try{if(t=Do&&(self.URL||self.webkitURL).createObjectURL(Do),!t)throw"";const n=new Worker(t,{name:e==null?void 0:e.name});return n.addEventListener("error",()=>{(self.URL||self.webkitURL).revokeObjectURL(t)}),n}catch{return new Worker("data:text/javascript;charset=utf-8,"+encodeURIComponent(No),{name:e==null?void 0:e.name})}}const Uo=`(function() {
  "use strict";
  function assertAlgorithm(algorithm, allowed) {
    if (!allowed.includes(algorithm)) {
      throw new Error(
        \`Unsupported algorithm: \${String(algorithm)}. Expected one of: \${allowed.join(", ")}.\`
      );
    }
  }
  function bufferStartsWith(buffer, prefix) {
    if (prefix.length > buffer.length) {
      return false;
    }
    for (let i = 0; i < prefix.length; i++) {
      if (buffer[i] !== prefix[i]) {
        return false;
      }
    }
    return true;
  }
  function bufferToHex(buffer) {
    return Array.from(new Uint8Array(buffer)).map((b) => b.toString(16).padStart(2, "0")).join("");
  }
  function concatBuffers(a, b) {
    const out = new Uint8Array(a.length + b.length);
    out.set(a, 0);
    out.set(b, a.length);
    return out;
  }
  function hexToBuffer(hex) {
    if (hex.length % 2 !== 0) {
      throw new Error(\`Hex string must have an even length. Got: \${hex}\`);
    }
    if (!/^[0-9a-fA-F]*$/.test(hex)) {
      throw new Error("Hex string contains non-hex characters.");
    }
    const buffer = new Uint8Array(hex.length / 2);
    for (let i = 0; i < buffer.length; i++) {
      buffer[i] = parseInt(hex.substring(i * 2, i * 2 + 2), 16);
    }
    return buffer;
  }
  async function delay(ms) {
    await new Promise((resolve) => setTimeout(resolve, ms));
  }
  function timeDuration(start) {
    return Math.floor((performance.now() - start) * 10) / 10;
  }
  var HmacAlgorithm = /* @__PURE__ */ ((HmacAlgorithm2) => {
    HmacAlgorithm2["SHA_256"] = "SHA-256";
    HmacAlgorithm2["SHA_384"] = "SHA-384";
    HmacAlgorithm2["SHA_512"] = "SHA-512";
    return HmacAlgorithm2;
  })(HmacAlgorithm || {});
  Object.values(HmacAlgorithm);
  const MAX_COUNTER = {
    string: Number.MAX_SAFE_INTEGER,
    uint32: 4294967295
  };
  function isValidCounter(n, mode) {
    return Number.isInteger(n) && n >= 0 && n <= MAX_COUNTER[mode];
  }
  class PasswordBuffer {
    constructor(nonce, mode = "uint32") {
      this.nonce = nonce;
      this.mode = mode;
      this.buffer = new Uint8Array(this.nonce.length + this.COUNTER_BYTES);
      this.buffer.set(this.nonce, 0);
      this.dataView = new DataView(this.buffer.buffer);
    }
    nonce;
    mode;
    COUNTER_BYTES = 4;
    buffer;
    dataView;
    encoder = new TextEncoder();
    /**
     * Appends the counter to the nonce buffer.
     * In 'string' mode, encodes the counter as a UTF-8 string.
     * In 'uint32' mode, writes the counter as a big-endian 32-bit integer.
     * Throws a RangeError unless the counter is an integer the mode encodes exactly.
     */
    setCounter(n) {
      if (!isValidCounter(n, this.mode)) {
        throw new RangeError(
          \`counter must be an integer from 0 to \${MAX_COUNTER[this.mode]}. Got: \${n}\`
        );
      }
      if (this.mode === "string") {
        return concatBuffers(this.nonce, this.encoder.encode(n.toString()));
      }
      this.dataView.setUint32(this.nonce.length, n, false);
      return this.buffer;
    }
  }
  function assertKeyPrefix(keyPrefix, keyLength) {
    if (typeof keyPrefix !== "string" || !/^[0-9a-fA-F]+$/.test(keyPrefix)) {
      throw new Error("keyPrefix must be a non-empty hex string.");
    }
    if (keyPrefix.length > keyLength * 2) {
      throw new Error(
        \`keyPrefix (\${keyPrefix.length} hex characters) must not be longer than the key (keyLength: \${keyLength} bytes).\`
      );
    }
  }
  async function solveChallenge(options) {
    const {
      challenge,
      controller,
      counterMode = "uint32",
      counterStart = 0,
      counterStep = 1,
      deriveKey: deriveKey2,
      timeout = 9e4
    } = options;
    const { nonce, keyLength = 32, keyPrefix, salt } = challenge.parameters;
    assertKeyPrefix(keyPrefix, keyLength);
    const nonceBuf = hexToBuffer(nonce);
    const saltBuf = hexToBuffer(salt);
    const keyPrefixHex = keyPrefix.toLowerCase();
    const keyPrefixBuf = keyPrefix.length % 2 === 0 ? hexToBuffer(keyPrefix) : null;
    const password = new PasswordBuffer(nonceBuf, counterMode);
    const start = performance.now();
    let counter = counterStart;
    let iterations = 0;
    let derivedKeyHex = "";
    let lastYield = start;
    while (true) {
      if (controller?.signal.aborted || timeout && iterations % 10 === 0 && performance.now() - start > timeout) {
        return null;
      }
      const { derivedKey } = await deriveKey2(
        challenge.parameters,
        saltBuf,
        password.setCounter(counter)
      );
      if (iterations % 10 === 0 && performance.now() - lastYield > 200) {
        await delay(0);
        lastYield = performance.now();
      }
      if (keyPrefixBuf ? bufferStartsWith(derivedKey, keyPrefixBuf) : bufferToHex(derivedKey).startsWith(keyPrefixHex)) {
        derivedKeyHex = bufferToHex(derivedKey);
        break;
      }
      counter = counter + counterStep;
      iterations = iterations + 1;
    }
    return {
      counter,
      derivedKey: derivedKeyHex,
      time: timeDuration(start)
    };
  }
  function handler(options) {
    const { deriveKey: deriveKey2 } = options;
    let controller = void 0;
    self.onmessage = async (message) => {
      const { challenge, counterMode, counterStart, counterStep, timeout, type } = message.data;
      if (type === "abort") {
        controller?.abort();
      } else if (type === "work") {
        controller = new AbortController();
        let solution;
        try {
          solution = await solveChallenge({
            challenge,
            controller,
            counterStart,
            counterStep,
            deriveKey: deriveKey2,
            counterMode,
            timeout
          });
        } catch (err) {
          return self.postMessage({ error: err });
        }
        self.postMessage(solution);
      }
    };
  }
  async function deriveKey(parameters, salt, password) {
    const { algorithm, keyLength = 32 } = parameters;
    assertAlgorithm(algorithm, ["SHA-256", "SHA-384", "SHA-512"]);
    const iterations = Math.max(1, parameters.cost);
    let derivedKey = concatBuffers(salt, password);
    for (let i = 0; i < iterations; i++) {
      derivedKey = new Uint8Array(await crypto.subtle.digest(algorithm, derivedKey));
    }
    return {
      parameters: {},
      derivedKey: derivedKey.slice(0, keyLength)
    };
  }
  handler({
    deriveKey
  });
})();
`,Ho=typeof self<"u"&&self.Blob&&new Blob(["(self.URL || self.webkitURL).revokeObjectURL(self.location.href);",Uo],{type:"text/javascript;charset=utf-8"});function Ha(e){let t;try{if(t=Ho&&(self.URL||self.webkitURL).createObjectURL(Ho),!t)throw"";const n=new Worker(t,{name:e==null?void 0:e.name});return n.addEventListener("error",()=>{(self.URL||self.webkitURL).revokeObjectURL(t)}),n}catch{return new Worker("data:text/javascript;charset=utf-8,"+encodeURIComponent(Uo),{name:e==null?void 0:e.name})}}return wc(`:root {
  --altcha-border-color: var(--altcha-color-neutral);
  --altcha-border-width: 1px;
  --altcha-border-radius: 6px;
  --altcha-color-base: light-dark(oklch(100% 0.00011 271.152), oklch(20.904% 0.00002 271.152));
  --altcha-color-base-content: light-dark(
  	oklch(20.904% 0.00002 271.152),
  	oklch(100% 0.00011 271.152)
  );
  --altcha-color-error: oklch(51.284% 0.20527 28.678);
  --altcha-color-error-content: oklch(100% 0.00011 271.152);
  --altcha-color-neutral: light-dark(oklch(83.591% 0.0001 271.152), oklch(46.04% 0.00005 271.152));
  --altcha-color-neutral-content: light-dark(
  	oklch(46.76% 0.00005 271.152),
  	oklch(100% 0.00011 271.152)
  );
  --altcha-color-primary: oklch(40.279% 0.2449 268.131);
  --altcha-color-primary-content: oklch(100% 0.00011 271.152);
  --altcha-color-success: oklch(55.748% 0.18968 142.511);
  --altcha-color-success-content: oklch(100% 0.00011 271.152);
  --altcha-checkbox-border-color: light-dark(
  	oklch(66.494% 0.00233 15.434),
  	oklch(51.028% 0.00006 271.152)
  );
  --altcha-checkbox-border-radius: 5px;
  --altcha-checkbox-border-width: var(--altcha-border-width);
  --altcha-checkbox-outline: 2px solid var(--altcha-checkbox-outline-color);
  --altcha-checkbox-outline-color: -webkit-focus-ring-color;
  --altcha-checkbox-outline-offset: 2px;
  --altcha-checkbox-size: 22px;
  --altcha-checkbox-transition-duration: var(--altcha-transition-duration);
  --altcha-input-background-color: var(--altcha-color-base);
  --altcha-input-border-radius: 3px;
  --altcha-input-border-width: 1px;
  --altcha-input-color: var(--altcha-color-base-content);
  --altcha-max-width: 320px;
  --altcha-padding: 0.75rem;
  --altcha-popover-arrow-size: 6px;
  --altcha-popover-color: var(--altcha-border-color);
  --altcha-shadow: drop-shadow(3px 3px 6px oklch(0% 0 0 / 0.2));
  --altcha-spinner-color: var(--altcha-color-base-content);
  --altcha-switch-background-color: var(--altcha-color-neutral);
  --altcha-switch-border-radius: calc(infinity * 1px);
  --altcha-switch-height: var(--altcha-checkbox-size);
  --altcha-switch-padding: 0.25rem;
  --altcha-switch-width: calc(var(--altcha-checkbox-size) * 1.75);
  --altcha-switch-toggle-border-radius: 100%;
  --altcha-switch-toggle-color: var(--altcha-color-neutral-content);
  --altcha-switch-toggle-size: calc(
  	var(--altcha-switch-height) - calc(var(--altcha-switch-padding) * 2)
  );
  --altcha-transition-duration: 0.6s;
  --altcha-z-index: 99999999;
  --altcha-z-index-popover: 999999999;
}

@supports (-moz-appearance: none) {
  :root {
    --altcha-checkbox-outline-color: var(--altcha-color-primary);
  }
}
.altcha {
  all: revert-layer;
  display: none;
  font-family: inherit;
  font-size: inherit;
  position: relative;
}
.altcha[data-visible] {
  display: block;
}
.altcha-popover, .altcha-popover * {
  all: revert-layer;
  box-sizing: border-box;
  font-family: inherit;
  font-size: inherit;
  line-height: 1.25;
}
.altcha * {
  all: revert-layer;
  box-sizing: border-box;
  font-family: inherit;
  font-size: inherit;
  line-height: 1.25;
}
.altcha a, .altcha-popover a {
  color: currentColor;
  text-decoration: none;
}
.altcha a:hover, .altcha-popover a:hover {
  color: currentColor;
}
.altcha-main {
  align-items: start;
  background-color: var(--altcha-color-base);
  border: var(--altcha-border-width, 1px) solid var(--altcha-border-color);
  border-radius: var(--altcha-border-radius, 0);
  color: var(--altcha-color-base-content);
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  justify-content: space-between;
  padding: var(--altcha-padding);
  max-width: var(--altcha-max-width, 100%);
}
.altcha-main > * {
  display: flex;
  width: 100%;
}
.altcha-main > *:first-child {
  flex-grow: 1;
}
.altcha-checkbox-wrap {
  align-items: center;
  display: flex;
  flex-direction: row;
  flex-grow: 1;
  gap: 0.5rem;
}
.altcha-checkbox-wrap > * {
  display: flex;
}
.altcha-logo {
  opacity: 0.7;
}
.altcha-footer {
  align-items: center;
  display: flex;
  flex-grow: 1;
  gap: 0.5rem;
  justify-content: flex-end;
  font-size: 0.7rem;
  opacity: 0.7;
}
.altcha-footer p {
  margin: 0;
  padding: 0;
}
.altcha-error {
  font-size: 0.85rem;
}
.altcha-button {
  align-items: center;
  background: var(--altcha-color-primary);
  border: var(--altcha-input-border-width) solid var(--altcha-color-primary);
  border-radius: var(--altcha-input-border-radius);
  color: var(--altcha-color-primary-content);
  cursor: pointer;
  display: flex;
  font-size: 0.9rem;
  gap: 0.5rem;
  padding: 0.35rem;
}
.altcha-button:focus {
  border-color: var(--altcha-color-primary);
  outline: var(--altcha-checkbox-outline);
  outline-offset: var(--altcha-checkbox-outline-offset);
}
.altcha-button > .altcha-spinner, .altcha-button > svg {
  height: 20px;
  width: 20px;
}
.altcha-button-secondary {
  background: transparent;
  border-color: var(--altcha-color-neutral);
  color: var(--altcha-color-neutral-content);
}
.altcha-input {
  background: var(--altcha-input-background-color);
  border: var(--altcha-input-border-width) solid var(--altcha-color-neutral);
  border-radius: var(--altcha-input-border-radius);
  color: var(--altcha-input-color);
  flex-grow: 1;
  font-size: 1rem;
  min-width: 0;
  padding: 0.25rem;
  width: auto;
}
.altcha-input:focus {
  border-color: var(--altcha-color-primary);
  outline: var(--altcha-checkbox-outline);
  outline-offset: var(--altcha-checkbox-outline-offset);
}
.altcha-spinner {
  animation: altcha-rotate 0.6s linear infinite;
  border-radius: 100%;
  border: var(--altcha-checkbox-border-width) solid var(--altcha-spinner-color);
  border-bottom-color: transparent;
  border-right-color: transparent;
  opacity: 0.7;
}
.altcha-popover {
  background-color: var(--altcha-color-base);
  border: var(--altcha-border-width) solid var(--altcha-border-color);
  border-radius: var(--altcha-border-radius);
  color: var(--altcha-color-base-content);
  filter: var(--altcha-shadow);
  position: absolute;
  left: calc(var(--altcha-padding) / 2);
  max-width: calc(var(--altcha-max-width) - var(--altcha-padding));
  top: calc(var(--altcha-padding) + var(--altcha-checkbox-size) + var(--altcha-popover-arrow-size));
  z-index: var(--altcha-z-index-popover);
}
.altcha-popover-arrow {
  border: var(--altcha-popover-arrow-size) solid transparent;
  border-bottom-color: var(--altcha-popover-color);
  content: "";
  height: 0;
  left: calc(var(--altcha-checkbox-size) / 2);
  position: absolute;
  top: calc(var(--altcha-popover-arrow-size) * -2);
  width: 0;
}
.altcha-popover-content {
  max-height: 100dvh;
  overflow: auto;
  padding: var(--altcha-padding);
}
.altcha-popover[data-top=true][data-display=standard] {
  bottom: calc(100% - (var(--altcha-padding) - var(--altcha-popover-arrow-size)));
  top: auto;
}
.altcha-popover[data-top=true][data-display=standard] .altcha-popover-arrow {
  border-bottom-color: transparent;
  border-top-color: var(--altcha-popover-color);
  bottom: calc(var(--altcha-popover-arrow-size) * -2);
  top: auto;
}
.altcha-popover[data-variant=error] {
  --altcha-popover-color: var(--altcha-color-error);
  background-color: var(--altcha-color-error);
  border-color: var(--altcha-color-error);
  color: var(--altcha-color-error-content);
}
.altcha-popover[data-variant=error] .altcha-popover-content {
  padding: calc(var(--altcha-padding) / 1.5) var(--altcha-padding);
}
.altcha-popover[data-display=overlay] {
  animation: altcha-overlay-slidein 0.5s forwards;
  left: 50%;
  position: fixed;
  top: 45%;
  transform: translate(-50%, -50%);
  width: var(--altcha-max-width);
  z-index: var(--altcha-z-index);
}
.altcha-popover[data-display=bottomsheet] {
  animation: altcha-bottomsheet-slideup 0.5s forwards;
  border-bottom-left-radius: 0;
  border-bottom-right-radius: 0;
  border-bottom: 0;
  bottom: -100%;
  left: 50%;
  position: fixed;
  top: auto;
  transform: translate(-50%, 0);
  width: var(--altcha-max-width);
  z-index: var(--altcha-z-index);
}
.altcha-popover[data-display=bottomsheet] .altcha-popover-content {
  padding-bottom: calc(var(--altcha-padding) * 2);
}
.altcha-popover-backdrop {
  background: var(--altcha-color-base-content);
  bottom: 0;
  left: 0;
  opacity: 0.1;
  position: fixed;
  right: 0;
  top: 0;
  transition: opacity 0.5s;
  z-index: var(--altcha-z-index);
}
.altcha-popover-close {
  color: var(--altcha-color-base-content);
  cursor: pointer;
  display: inline-block;
  font-size: 1rem;
  height: 1.25rem;
  line-height: 0.95;
  position: absolute;
  right: 0;
  text-align: center;
  text-shadow: 0 0 1px var(--altcha-color-base);
  top: -1.5rem;
  width: 1.25rem;
  z-index: var(--altcha-z-index);
}
[dir=rtl] .altcha-popover {
  left: auto;
  right: calc(var(--altcha-padding) / 2);
}
[dir=rtl] .altcha-popover-arrow {
  left: auto;
  right: calc(var(--altcha-checkbox-size) / 2);
}
[dir=rtl] .altcha-popover-close {
  left: 0;
  right: auto;
}
.altcha-popover[data-display=bottomsheet] .altcha-footer, .altcha-popover[data-display=overlay] .altcha-footer {
  align-items: center;
  justify-content: center;
  padding-top: 1rem;
  gap: 0.5rem;
}
.altcha-popover[data-display=bottomsheet] .altcha-footer svg, .altcha-popover[data-display=overlay] .altcha-footer svg {
  height: 18px;
  width: 18px;
  vertical-align: middle;
}
.altcha-code-challenge > form {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.altcha-code-challenge-title {
  font-weight: 600;
}
.altcha-code-challenge-text {
  font-size: 0.85rem;
}
.altcha-code-challenge-image {
  background: white;
  border: var(--altcha-input-border-width) solid var(--altcha-color-neutral);
  border-radius: var(--altcha-input-border-radius);
  object-fit: contain;
  height: 50px;
}
.altcha-code-challenge-row {
  display: flex;
  gap: 0.5rem;
}
.altcha-code-challenge-buttons {
  align-items: center;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-top: var(--altcha-padding);
  justify-content: space-between;
}
.altcha-code-challenge-buttons button {
  justify-content: center;
  width: 100%;
}
.altcha-checkbox {
  cursor: pointer;
  height: var(--altcha-checkbox-size);
  position: relative;
  width: var(--altcha-checkbox-size);
}
.altcha-checkbox input {
  appearance: none;
  background: var(--altcha-input-background-color);
  border: var(--altcha-checkbox-border-width, 2px) solid var(--altcha-checkbox-border-color);
  border-radius: var(--altcha-checkbox-border-radius);
  cursor: pointer;
  height: var(--altcha-checkbox-size);
  left: 0;
  margin: 0;
  padding: 0;
  position: absolute;
  top: 0;
  width: var(--altcha-checkbox-size);
}
@supports (hanging-punctuation: first) and (font: -apple-system-body) and (-webkit-appearance: none) {
  .altcha-checkbox input {
    /* Safari-only: fixes focus outline */
  }
  .altcha-checkbox input:focus {
    outline-width: 2px;
    outline-style: solid;
  }
}
.altcha-checkbox input:before {
  border-radius: var(--altcha-checkbox-border-radius);
  content: "";
  width: 100%;
  height: 100%;
  background: var(--altcha-color-neutral);
  display: block;
  transform: scale(0);
}
.altcha-checkbox input:checked {
  background-color: var(--altcha-color-success);
  border-color: var(--altcha-color-success);
}
.altcha-checkbox input:checked::before {
  background-color: var(--altcha-color-success);
  opacity: 0;
  transform: scale(2.2);
  transition: all var(--altcha-checkbox-transition-duration) ease;
  transition-delay: 0.1s;
}
.altcha-checkbox svg {
  --altcha-radio-svg-size: calc(var(--altcha-checkbox-size) * 0.5);
  --altcha-radio-svg-offset: calc(var(--altcha-checkbox-size) * 0.25);
  fill: none;
  left: var(--altcha-radio-svg-offset);
  height: var(--altcha-radio-svg-size);
  opacity: 0;
  position: absolute;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-dasharray: 16px;
  stroke-dashoffset: 16px;
  top: var(--altcha-radio-svg-offset);
  transform: translate3d(0, 0, 0);
  width: var(--altcha-radio-svg-size);
}
.altcha-checkbox input:checked + svg {
  color: var(--altcha-color-success-content);
  opacity: 1;
  stroke-dashoffset: 0;
  transition: all var(--altcha-checkbox-transition-duration) ease;
  transition-delay: 0.1s;
}
.altcha-checkbox-spinner {
  display: none;
  left: 0;
  height: var(--altcha-checkbox-size);
  position: absolute;
  top: 0;
  width: var(--altcha-checkbox-size);
}
.altcha-checkbox[data-loading=true] input {
  appearance: none;
  opacity: 0;
  pointer-events: none;
}
.altcha-checkbox[data-loading=true] .altcha-checkbox-spinner {
  display: block;
}
.altcha-checkbox-native {
  height: var(--altcha-checkbox-size);
  position: relative;
  width: var(--altcha-checkbox-size);
}
.altcha-checkbox-native input {
  height: var(--altcha-checkbox-size);
  margin: 0;
  width: var(--altcha-checkbox-size);
}
.altcha-checkbox-native-spinner {
  display: none;
  left: 0;
  height: var(--altcha-checkbox-size);
  position: absolute;
  top: 0;
  width: var(--altcha-checkbox-size);
}
.altcha-checkbox-native[data-loading=true] input {
  appearance: none;
  opacity: 0;
  pointer-events: none;
}
.altcha-checkbox-native[data-loading=true] .altcha-checkbox-native-spinner {
  display: block;
}
.altcha-switch {
  align-items: center;
  border-radius: var(--altcha-switch-border-radius);
  background-color: var(--altcha-switch-background-color);
  display: flex;
  height: var(--altcha-switch-height);
  padding: var(--altcha-switch-padding);
  position: relative;
  width: var(--altcha-switch-width);
}
.altcha-switch:focus-within {
  outline: var(--altcha-checkbox-outline);
  outline-offset: var(--altcha-checkbox-outline-offset);
}
.altcha-switch input {
  appearance: none;
  cursor: pointer;
  height: 100%;
  left: 0;
  opacity: 0;
  position: absolute;
  top: 0;
  width: 100%;
}
.altcha-switch-toggle {
  align-items: center;
  background-color: var(--altcha-switch-toggle-color);
  border-radius: var(--altcha-switch-toggle-border-radius);
  cursor: pointer;
  display: flex;
  height: var(--altcha-switch-toggle-size);
  justify-content: center;
  left: var(--altcha-switch-padding);
  position: absolute;
  transition: width 150ms ease-out, left 150ms ease-out;
  width: var(--altcha-switch-toggle-size);
}
.altcha-switch-spinner {
  display: none;
  height: var(--altcha-switch-toggle-size);
  width: var(--altcha-switch-toggle-size);
}
.altcha-switch[data-loading=true] {
  pointer-events: none;
}
.altcha-switch[data-loading=true] .altcha-switch-spinner {
  display: block;
}
.altcha-switch[data-loading=true] .altcha-switch-toggle {
  background-color: transparent;
  left: calc(50% - var(--altcha-switch-toggle-size) / 2);
}
[data-state=verified] .altcha-switch {
  --altcha-switch-background-color: var(--altcha-color-success);
}
[data-state=verified] .altcha-switch-toggle {
  background-color: var(--altcha-color-success-content);
  left: calc(100% - var(--altcha-switch-height) + var(--altcha-switch-padding));
}
[dir=rtl] .altcha-switch-toggle {
  left: calc(100% - var(--altcha-switch-height) + var(--altcha-switch-padding));
}
[dir=rtl][data-state=verified] .altcha-switch-toggle {
  left: var(--altcha-switch-padding);
}
.altcha-floating-arrow {
  border: 6px solid transparent;
  border-bottom-color: var(--altcha-border-color);
  content: "";
  height: 0;
  left: 12px;
  position: absolute;
  top: -12px;
  width: 0;
}
.altcha-overlay-backdrop {
  bottom: 0;
  left: 0;
  position: fixed;
  right: 0;
  top: 0;
  transition: opacity var(--altcha-transition-duration);
  z-index: var(--altcha-z-index);
}
.altcha-overlay-close {
  display: inline-block;
  color: currentColor;
  cursor: pointer;
  font-size: 1rem;
  height: 1rem;
  line-height: 0.85;
  position: absolute;
  right: 0;
  text-align: center;
  text-shadow: 0 0 1px var(--altcha-color-base);
  top: -1.5rem;
  width: 1rem;
  z-index: var(--altcha-z-index);
}
.altcha[data-display=overlay] {
  animation: altcha-overlay-slidein var(--altcha-transition-duration) forwards;
  filter: var(--altcha-shadow);
  left: 50%;
  opacity: 0;
  position: fixed;
  top: 45%;
  transform: translate(-50%, -50%);
  z-index: var(--altcha-z-index);
}
.altcha[data-display=overlay] .altcha-main {
  width: var(--altcha-max-width);
}
.altcha[data-display=floating] {
  display: none;
  filter: var(--altcha-shadow);
  left: var(--altcha-floating-left, -100%);
  position: fixed;
  top: var(--altcha-floating-top, -100%);
  z-index: var(--altcha-z-index);
}
.altcha[data-display=floating] .altcha-main {
  width: var(--altcha-max-width);
}
.altcha[data-display=floating][data-floating-position=top] .altcha-floating-arrow {
  border-bottom-color: transparent;
  border-top-color: var(--altcha-border-color);
  bottom: -12px;
  top: auto;
}
.altcha[data-display=floating][data-visible] {
  display: flex;
}
.altcha[data-display=bar] {
  bottom: -100%;
  filter: var(--altcha-shadow);
  left: 0;
  position: fixed;
  right: 0;
  transition: bottom var(--altcha-transition-duration), top var(--altcha-transition-duration);
  z-index: var(--altcha-z-index);
}
.altcha[data-display=bar] .altcha-main {
  align-items: center;
  border-radius: 0;
  border-width: var(--altcha-border-width) 0 0 0;
  flex-direction: row;
  max-width: 100% !important;
}
.altcha[data-display=bar] .altcha-main > * {
  width: auto;
}
.altcha[data-display=bar][data-placement=top] {
  bottom: auto;
  top: -100%;
}
.altcha[data-display=bar][data-placement=top] .altcha-main {
  border-width: 0 0 var(--altcha-border-width) 0;
}
.altcha[data-display=bar][data-placement=bottom]:not([data-state=unverified]) {
  bottom: 0;
}
.altcha[data-display=bar][data-placement=top]:not([data-state=unverified]) {
  top: 0;
}
.altcha[data-display=invisible] {
  display: none;
}

@keyframes altcha-rotate {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}
@keyframes altcha-bottomsheet-slideup {
  100% {
    bottom: 0;
  }
}
@keyframes altcha-overlay-slidein {
  100% {
    opacity: 1;
    top: 50%;
  }
}`),$altcha.algorithms.set("SHA-256",()=>new Ha),$altcha.algorithms.set("SHA-384",()=>new Ha),$altcha.algorithms.set("SHA-512",()=>new Ha),$altcha.algorithms.set("PBKDF2/SHA-256",()=>new Ua),$altcha.algorithms.set("PBKDF2/SHA-384",()=>new Ua),$altcha.algorithms.set("PBKDF2/SHA-512",()=>new Ua),os}_u();
