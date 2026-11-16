const P="rem-theme",I=["light","dark","system"],g=document.documentElement,V=window.matchMedia("(prefers-color-scheme: dark)");function w(){const t=localStorage.getItem(P);return I.includes(t)?t:"system"}function A(t){return t==="light"||t==="dark"?t:V.matches?"dark":"light"}function B(t){document.querySelectorAll("[data-theme-set]").forEach(e=>{e.setAttribute("aria-pressed",String(e.dataset.themeSet===t))}),document.querySelectorAll("[data-theme-toggle]").forEach(e=>{const a=A(t)==="dark";e.setAttribute("aria-label",a?"Cambiar a tema claro":"Cambiar a tema oscuro"),e.dataset.themeState=a?"dark":"light"})}function x(t){const e=A(t);return g.classList.toggle("dark",e==="dark"),g.classList.toggle("light",e==="light"),g.dataset.themePreference=t,B(t),document.dispatchEvent(new CustomEvent("rem:themechange",{detail:{preference:t,effective:e}})),e}function k(t){I.includes(t)&&(localStorage.setItem(P,t),x(t))}function U(){x(w()),document.querySelectorAll("[data-theme-set]").forEach(t=>{t.addEventListener("click",()=>k(t.dataset.themeSet))}),document.querySelectorAll("[data-theme-toggle]").forEach(t=>{t.addEventListener("click",()=>{k(A(w())==="dark"?"light":"dark")})}),V.addEventListener("change",()=>{w()==="system"&&x("system")})}const _="rem-sidebar",y=document.documentElement;function D(){return!window.matchMedia("(min-width: 64rem)").matches}function S(t,e){document.querySelectorAll("[data-sidebar-toggle]").forEach(a=>{a.setAttribute("aria-expanded",String(e))}),t&&(t.dataset.state=e?"open":"closed")}function f(t){y.classList.remove("sidebar-open"),S(t,!1)}function j(t){var e;y.classList.add("sidebar-open"),S(t,!0),(e=t==null?void 0:t.querySelector("a, button"))==null||e.focus({preventScroll:!0})}function $(){const t=document.querySelector("[data-sidebar]"),e=document.querySelector("[data-sidebar-overlay]");document.querySelectorAll("[data-sidebar-toggle]").forEach(a=>{a.addEventListener("click",()=>{y.classList.contains("sidebar-open")?f(t):j(t)})}),document.querySelectorAll("[data-sidebar-close]").forEach(a=>{a.addEventListener("click",()=>f(t))}),e==null||e.addEventListener("click",()=>f(t)),document.querySelectorAll("[data-sidebar-collapse]").forEach(a=>{a.addEventListener("click",()=>{const n=y.classList.toggle("sidebar-collapsed");localStorage.setItem(_,n?"collapsed":"expanded"),a.setAttribute("aria-expanded",String(!n)),a.setAttribute("aria-label",n?"Expandir la barra lateral":"Colapsar la barra lateral")})}),document.querySelectorAll("[data-nav-group-toggle]").forEach(a=>{a.addEventListener("click",()=>{const n=a.getAttribute("aria-expanded")==="true";a.setAttribute("aria-expanded",String(!n));const r=document.getElementById(a.getAttribute("aria-controls"));r&&(r.hidden=n)})}),document.addEventListener("keydown",a=>{var n;a.key==="Escape"&&y.classList.contains("sidebar-open")&&(f(t),(n=document.querySelector("[data-sidebar-toggle]"))==null||n.focus({preventScroll:!0}))}),window.matchMedia("(min-width: 64rem)").addEventListener("change",a=>{a.matches&&f(t)}),S(t,!D())}function z(){document.querySelectorAll("[data-tabs]").forEach(t=>{const e=Array.from(t.querySelectorAll("[data-tab]")),a=Array.from(t.querySelectorAll("[data-panel]"));if(e.length===0)return;const n=(o,s=!0)=>{e.forEach(c=>{const d=c===o;c.setAttribute("aria-selected",String(d)),c.tabIndex=d?0:-1}),a.forEach(c=>{c.hidden=c.dataset.panel!==o.dataset.tab}),s&&o.focus()};e.forEach(o=>{o.addEventListener("click",()=>n(o,!1)),o.addEventListener("keydown",s=>{const c=e.indexOf(o);let d=null;switch(s.key){case"ArrowRight":d=e[(c+1)%e.length];break;case"ArrowLeft":d=e[(c-1+e.length)%e.length];break;case"Home":d=e[0];break;case"End":d=e[e.length-1];break;default:return}s.preventDefault(),n(d)})});const r=e.some(o=>o.getAttribute("aria-selected")==="true");n(r?e.find(o=>o.getAttribute("aria-selected")==="true"):e[0],!1)})}const G="modulepreload",F=function(t,e){return new URL(t,e).href},L={},Y=function(e,a,n){let r=Promise.resolve();if(a&&a.length>0){let s=function(i){return Promise.all(i.map(h=>Promise.resolve(h).then(u=>({status:"fulfilled",value:u}),u=>({status:"rejected",reason:u}))))};const c=document.getElementsByTagName("link"),d=document.querySelector("meta[property=csp-nonce]"),p=(d==null?void 0:d.nonce)||(d==null?void 0:d.getAttribute("nonce"));r=s(a.map(i=>{if(i=F(i,n),i in L)return;L[i]=!0;const h=i.endsWith(".css"),u=h?'[rel="stylesheet"]':"";if(!!n)for(let m=c.length-1;m>=0;m--){const M=c[m];if(M.href===i&&(!h||M.rel==="stylesheet"))return}else if(document.querySelector(`link[href="${i}"]${u}`))return;const l=document.createElement("link");if(l.rel=h?"stylesheet":G,h||(l.as="script"),l.crossOrigin="",l.href=i,p&&l.setAttribute("nonce",p),document.head.appendChild(l),h)return new Promise((m,M)=>{l.addEventListener("load",m),l.addEventListener("error",()=>M(new Error(`Unable to preload CSS for ${i}`)))})}))}function o(s){const c=new Event("vite:preloadError",{cancelable:!0});if(c.payload=s,window.dispatchEvent(c),!c.defaultPrevented)throw s}return r.then(s=>{for(const c of s||[])c.status==="rejected"&&o(c.reason);return e().catch(o)})};let v=null;const K={success:{duration:4500},error:{duration:8e3,dismissible:!0},info:{duration:5e3}};async function W(){if(v)return v;const{Notyf:t}=await Y(async()=>{const{Notyf:e}=await import("./notyf.es-D99u0-HC.js");return{Notyf:e}},[],import.meta.url);return v=new t({duration:4500,ripple:!1,dismissible:!1,position:{x:"right",y:"top"},types:Object.entries(K).map(([e,a])=>({type:e,...a}))}),v}async function E(t,e="success"){if(t)try{const a=await W();typeof a[e]=="function"?a[e](t):a.success(t)}catch(a){console.error("[avisos] no se pudo cargar Notyf",a)}}function X(){window.remToast=E;const t=document.querySelector("[data-flashes]");t&&Array.from(t.querySelectorAll("[data-message]")).forEach((e,a)=>{window.setTimeout(()=>{E(e.dataset.message,e.dataset.type||"success")},a*250)}),document.querySelectorAll("[data-toast]").forEach(e=>{e.addEventListener("click",()=>{E(e.dataset.toast,e.dataset.toastType||"success")})})}function J(){document.querySelectorAll("[data-modal-open]").forEach(t=>{t.addEventListener("click",()=>{const e=document.getElementById(t.dataset.modalOpen);e&&typeof e.showModal=="function"&&!e.open&&e.showModal()})}),document.querySelectorAll("dialog[data-modal]").forEach(t=>{t.querySelectorAll("[data-modal-close]").forEach(e=>{e.addEventListener("click",()=>t.close())}),t.addEventListener("click",e=>{e.target===t&&t.close()})})}/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const O={xmlns:"http://www.w3.org/2000/svg",width:24,height:24,viewBox:"0 0 24 24",fill:"none",stroke:"currentColor","stroke-width":2,"stroke-linecap":"round","stroke-linejoin":"round"};/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const R=([t,e,a])=>{const n=document.createElementNS("http://www.w3.org/2000/svg",t);return Object.keys(e).forEach(r=>{n.setAttribute(r,String(e[r]))}),a!=null&&a.length&&a.forEach(r=>{const o=R(r);n.appendChild(o)}),n},Q=(t,e={})=>{const n={...O,...e};return R(["svg",n,t])};/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Z=(...t)=>t.filter((e,a,n)=>!!e&&e.trim()!==""&&n.indexOf(e)===a).join(" ").trim();/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const tt=t=>{for(const e in t)if(e.startsWith("aria-")||e==="role"||e==="title")return!0;return!1};/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const et=t=>{let e="",a=!1;for(const n of t){if(n==="-"||n==="_"||n<=" "){a=e.length>0;continue}e.length===0?e+=n.toLowerCase():e+=a?n.toUpperCase():n,a=!1}return e};/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const at=t=>{const e=et(t);return e.charAt(0).toUpperCase()+e.slice(1)};/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const nt=t=>Array.from(t.attributes).reduce((e,a)=>(e[a.name]=a.value,e),{}),C=t=>typeof t=="string"?t:!t||!t.class?"":t.class&&typeof t.class=="string"?t.class.split(" "):t.class&&Array.isArray(t.class)?t.class:"",q=(t,{nameAttr:e,icons:a,attrs:n})=>{var l;const r=t.getAttribute(e);if(r==null)return;const o=at(r),s=a[o];if(!s)return console.warn(`${t.outerHTML} icon name was not found in the provided icons object.`);const c=nt(t),d=tt(c)?{}:{"aria-hidden":"true"},p={...O,"data-lucide":r,...d,...n,...c},i=C(c),h=C(n),u=Z("lucide",`lucide-${r}`,...i,...h);u&&Object.assign(p,{class:u});const b=Q(s,p);return(l=t.parentNode)==null?void 0:l.replaceChild(b,t)};/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ct=[["path",{d:"M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const rt=[["path",{d:"M5 12h14"}],["path",{d:"m12 5 7 7-7 7"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ot=[["path",{d:"m21 16-4 4-4-4"}],["path",{d:"M17 20V4"}],["path",{d:"m3 8 4-4 4 4"}],["path",{d:"M7 4v16"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const st=[["circle",{cx:"12",cy:"12",r:"10"}],["path",{d:"M4.929 4.929 19.07 19.071"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const dt=[["path",{d:"M10.268 21a2 2 0 0 0 3.464 0"}],["path",{d:"M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const it=[["path",{d:"M12 10h.01"}],["path",{d:"M12 14h.01"}],["path",{d:"M12 6h.01"}],["path",{d:"M16 10h.01"}],["path",{d:"M16 14h.01"}],["path",{d:"M16 6h.01"}],["path",{d:"M8 10h.01"}],["path",{d:"M8 14h.01"}],["path",{d:"M8 6h.01"}],["path",{d:"M9 22v-3a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3"}],["rect",{x:"4",y:"2",width:"16",height:"20",rx:"2"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const lt=[["path",{d:"M8 2v3"}],["path",{d:"M16 2v3"}],["rect",{x:"3",y:"3",width:"18",height:"18",rx:"2"}],["path",{d:"M3 9h18"}],["path",{d:"m9 15 2 2 4-4"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ht=[["path",{d:"M8 2v3"}],["path",{d:"M16 2v3"}],["rect",{x:"3",y:"3",width:"18",height:"18",rx:"2"}],["path",{d:"M3 9h18"}],["path",{d:"M8 13h.01"}],["path",{d:"M12 13h.01"}],["path",{d:"M16 13h.01"}],["path",{d:"M8 17h.01"}],["path",{d:"M12 17h.01"}],["path",{d:"M16 17h.01"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ut=[["path",{d:"M8 2v3"}],["path",{d:"M16 2v3"}],["rect",{x:"3",y:"3",width:"18",height:"18",rx:"2"}],["path",{d:"M3 9h18"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const pt=[["path",{d:"M3 3v16a2 2 0 0 0 2 2h16"}],["path",{d:"M18 17V9"}],["path",{d:"M13 17V5"}],["path",{d:"M8 17v-3"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const mt=[["path",{d:"M20 6 9 17l-5-5"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ft=[["path",{d:"m6 9 6 6 6-6"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const yt=[["path",{d:"m15 18-6-6 6-6"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Mt=[["path",{d:"m9 18 6-6-6-6"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const vt=[["path",{d:"m11 17-5-5 5-5"}],["path",{d:"m18 17-5-5 5-5"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const gt=[["circle",{cx:"12",cy:"12",r:"10"}],["path",{d:"m16 9-5.5 5.5L8 12"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const wt=[["circle",{cx:"12",cy:"12",r:"10"}],["line",{x1:"12",x2:"12",y1:"8",y2:"12"}],["line",{x1:"12",x2:"12.01",y1:"16",y2:"16"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Et=[["circle",{cx:"12",cy:"12",r:"10"}],["path",{d:"m15 9-6 6"}],["path",{d:"m9 9 6 6"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const xt=[["circle",{cx:"12",cy:"12",r:"10"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const At=[["rect",{width:"8",height:"4",x:"8",y:"2",rx:"1",ry:"1"}],["path",{d:"M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"}],["path",{d:"m9 14 2 2 4-4"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const St=[["circle",{cx:"12",cy:"12",r:"10"}],["path",{d:"M12 6v6l4 2"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const bt=[["path",{d:"M12 15V3"}],["path",{d:"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"}],["path",{d:"m7 10 5 5 5-5"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const kt=[["circle",{cx:"12",cy:"12",r:"1"}],["circle",{cx:"19",cy:"12",r:"1"}],["circle",{cx:"5",cy:"12",r:"1"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Lt=[["path",{d:"M15 3h6v6"}],["path",{d:"M10 14 21 3"}],["path",{d:"M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ct=[["path",{d:"M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"}],["circle",{cx:"12",cy:"12",r:"3"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const qt=[["path",{d:"M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"}],["path",{d:"M14 2v5a1 1 0 0 0 1 1h5"}],["path",{d:"M8 13h2"}],["path",{d:"M14 13h2"}],["path",{d:"M8 17h2"}],["path",{d:"M14 17h2"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ht=[["path",{d:"M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"}],["path",{d:"M14 2v5a1 1 0 0 0 1 1h5"}],["path",{d:"M10 9H8"}],["path",{d:"M16 13H8"}],["path",{d:"M16 17H8"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Tt=[["path",{d:"M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"}],["path",{d:"M14 2v5a1 1 0 0 0 1 1h5"}],["path",{d:"M12 12v6"}],["path",{d:"m15 15-3-3-3 3"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Pt=[["rect",{width:"18",height:"18",x:"3",y:"3",rx:"2"}],["path",{d:"M3 9h18"}],["path",{d:"M3 15h18"}],["path",{d:"M9 3v18"}],["path",{d:"M15 3v18"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const It=[["path",{d:"M12 7v4"}],["path",{d:"M14 21v-3a2 2 0 0 0-4 0v3"}],["path",{d:"M14 9h-4"}],["path",{d:"M18 11h2a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-9a2 2 0 0 1 2-2h2"}],["path",{d:"M18 21V5a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Vt=[["path",{d:"M5 22h14"}],["path",{d:"M5 2h14"}],["path",{d:"M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"}],["path",{d:"M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ot=[["path",{d:"M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"}],["path",{d:"M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Rt=[["polyline",{points:"22 12 16 12 14 15 10 15 8 12 2 12"}],["path",{d:"M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Nt=[["rect",{width:"7",height:"9",x:"3",y:"3",rx:"1"}],["rect",{width:"7",height:"5",x:"14",y:"3",rx:"1"}],["rect",{width:"7",height:"9",x:"14",y:"12",rx:"1"}],["rect",{width:"7",height:"5",x:"3",y:"16",rx:"1"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Bt=[["path",{d:"M11 5h10"}],["path",{d:"M11 12h10"}],["path",{d:"M11 19h10"}],["path",{d:"M4 4h1v5"}],["path",{d:"M4 9h2"}],["path",{d:"M6.5 20H3.4c0-1 2.6-1.925 2.6-3.5a1.5 1.5 0 0 0-2.6-1.02"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ut=[["path",{d:"M3 5h.01"}],["path",{d:"M3 12h.01"}],["path",{d:"M3 19h.01"}],["path",{d:"M8 5h13"}],["path",{d:"M8 12h13"}],["path",{d:"M8 19h13"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const _t=[["rect",{width:"18",height:"11",x:"3",y:"11",rx:"2",ry:"2"}],["path",{d:"M7 11V7a5 5 0 0 1 10 0v4"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Dt=[["path",{d:"m16 17 5-5-5-5"}],["path",{d:"M21 12H9"}],["path",{d:"M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const jt=[["path",{d:"m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"}],["rect",{x:"2",y:"4",width:"20",height:"16",rx:"2"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const $t=[["path",{d:"M4 5h16"}],["path",{d:"M4 12h16"}],["path",{d:"M4 19h16"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const zt=[["path",{d:"M5 12h14"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Gt=[["rect",{width:"20",height:"14",x:"2",y:"3",rx:"2"}],["line",{x1:"8",x2:"16",y1:"21",y2:"21"}],["line",{x1:"12",x2:"12",y1:"17",y2:"21"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ft=[["path",{d:"M20.985 12.486a9 9 0 1 1-9.473-9.472c.405-.022.617.46.402.803a6 6 0 0 0 8.268 8.268c.344-.215.825-.004.803.401"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Yt=[["rect",{width:"18",height:"18",x:"3",y:"3",rx:"2"}],["path",{d:"M9 3v18"}],["path",{d:"m16 15-3-3 3-3"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Kt=[["line",{x1:"19",x2:"5",y1:"5",y2:"19"}],["circle",{cx:"6.5",cy:"6.5",r:"2.5"}],["circle",{cx:"17.5",cy:"17.5",r:"2.5"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Wt=[["path",{d:"M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"}],["path",{d:"m15 5 4 4"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Xt=[["path",{d:"M5 12h14"}],["path",{d:"M12 5v14"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Jt=[["path",{d:"m21 21-4.34-4.34"}],["circle",{cx:"11",cy:"11",r:"8"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Qt=[["circle",{cx:"12",cy:"12",r:"4"}],["path",{d:"M12 2v2"}],["path",{d:"M12 20v2"}],["path",{d:"m4.93 4.93 1.41 1.41"}],["path",{d:"m17.66 17.66 1.41 1.41"}],["path",{d:"M2 12h2"}],["path",{d:"M20 12h2"}],["path",{d:"m6.34 17.66-1.41 1.41"}],["path",{d:"m19.07 4.93-1.41 1.41"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Zt=[["path",{d:"M12 3v18"}],["rect",{width:"18",height:"18",x:"3",y:"3",rx:"2"}],["path",{d:"M3 9h18"}],["path",{d:"M3 15h18"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const te=[["path",{d:"m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"}],["path",{d:"M12 9v4"}],["path",{d:"M12 17h.01"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ee=[["path",{d:"M12 3v12"}],["path",{d:"m17 8-5-5-5 5"}],["path",{d:"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ae=[["path",{d:"M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"}],["circle",{cx:"12",cy:"7",r:"4"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ne=[["path",{d:"M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"}],["path",{d:"M16 3.128a4 4 0 0 1 0 7.744"}],["path",{d:"M22 21v-2a4 4 0 0 0-3-3.87"}],["circle",{cx:"9",cy:"7",r:"4"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ce=[["path",{d:"M18 6 6 18"}],["path",{d:"m6 6 12 12"}]];/**
 * @license lucide v1.73.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const N=({icons:t={},nameAttr:e="data-lucide",attrs:a={},root:n=document,inTemplates:r}={})=>{if(!Object.values(t).length)throw new Error(`Please provide an icons object.
If you want to use all the icons you can import it like:
 \`import { createIcons, icons } from 'lucide';
lucide.createIcons({icons});\``);if(typeof n>"u")throw new Error("`createIcons()` only works in a browser environment.");if(Array.from(n.querySelectorAll(`[${e}]`)).forEach(s=>q(s,{nameAttr:e,icons:t,attrs:a})),r&&Array.from(n.querySelectorAll("template")).forEach(c=>N({icons:t,nameAttr:e,attrs:a,root:c.content,inTemplates:r})),e==="data-lucide"){const s=n.querySelectorAll("[icon-name]");s.length>0&&(console.warn("[Lucide] Some icons were found with the now deprecated icon-name attribute. These will still be replaced for backwards compatibility, but will no longer be supported in v1.0 and you should switch to data-lucide"),Array.from(s).forEach(c=>q(c,{nameAttr:"icon-name",icons:t,attrs:a})))}},re={Activity:ct,ArrowRight:rt,ArrowUpDown:ot,Ban:st,Bell:dt,Building:it,Calendar:ut,CalendarCheck:lt,CalendarDays:ht,ChartColumn:pt,Check:mt,ChevronDown:ft,ChevronLeft:yt,ChevronRight:Mt,ChevronsLeft:vt,Circle:xt,CircleCheck:gt,CircleExclamationPoint:wt,CircleX:Et,ClipboardCheck:At,Clock:St,Download:bt,Ellipsis:kt,ExternalLink:Lt,Eye:Ct,FileSpreadsheet:qt,FileText:Ht,FileUp:Tt,Grid3x3:Pt,Hospital:It,Hourglass:Vt,House:Ot,Inbox:Rt,LayoutDashboard:Nt,List:Ut,ListOrdered:Bt,Lock:_t,LogOut:Dt,Mail:jt,Menu:$t,Minus:zt,Monitor:Gt,Moon:Ft,PanelLeftClose:Yt,Pencil:Wt,Percent:Kt,Plus:Xt,Search:Jt,Sun:Qt,Table:Zt,TriangleExclamationPoint:te,Upload:ee,User:ae,Users:ne,X:ce};function H(t=document){N({icons:re,root:t})}function T(){U(),$(),z(),X(),J(),H(),window.remRenderIcons=H}document.readyState==="loading"?document.addEventListener("DOMContentLoaded",T,{once:!0}):T();
