/**
 * Real 3D garment preview for the Design Studio (custom-design.php).
 *
 * The 2D canvas stays the editor; this shows the same artwork on a 3D model,
 * live. Artwork is laid on the garment as a decal (projected onto the mesh
 * surface), so it works with any model regardless of how its UVs are laid out.
 *
 * Exposes window.Design3D; custom-design.php drives it through update3D().
 * If WebGL or three.js is unavailable window.Design3D is never set, and a model
 * that fails to load is reported by canShow(); either way the page keeps its
 * old flat preview.
 */
import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { DecalGeometry } from 'three/addons/geometries/DecalGeometry.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';
import { DRACOLoader } from 'three/addons/loaders/DRACOLoader.js';
import * as SkeletonUtils from 'three/addons/utils/SkeletonUtils.js';

const SIDES = ['front', 'back'];
const SLEEVES = ['left', 'right'];   // the wearer's left and right
const HEIGHT = 2;          // every model is scaled to this height
const GAP = 0.15;          // space between the two garments of a couple set

const container = document.getElementById('garment3d');
const statusEl  = document.getElementById('garment3dStatus');

let renderer;
try {
    renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
} catch (e) {
    renderer = null;
}

if (container && renderer) {
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    container.prepend(renderer.domElement);

    const scene = new THREE.Scene();
    const pmrem = new THREE.PMREMGenerator(renderer);
    scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
    const key = new THREE.DirectionalLight(0xffffff, 1.2);
    key.position.set(2, 3, 4);
    // A softer light from behind, so the back is not dark when the garment
    // is turned round (dark colours especially).
    const back = new THREE.DirectionalLight(0xffffff, 1.3);
    back.position.set(-2, 2.5, -4);
    scene.add(key, back, new THREE.AmbientLight(0xffffff, 0.25));

    // A soft round shadow under each garment, so it sits on a floor instead
    // of floating. One shared texture: a radial fade from dark to clear.
    const shadowTexture = (() => {
        const cv = document.createElement('canvas');
        cv.width = cv.height = 128;
        const c = cv.getContext('2d');
        const g = c.createRadialGradient(64, 64, 0, 64, 64, 64);
        g.addColorStop(0, 'rgba(0,0,0,0.38)');
        g.addColorStop(0.55, 'rgba(0,0,0,0.16)');
        g.addColorStop(1, 'rgba(0,0,0,0)');
        c.fillStyle = g;
        c.fillRect(0, 0, 128, 128);
        const t = new THREE.CanvasTexture(cv);
        t.colorSpace = THREE.SRGBColorSpace;
        return t;
    })();
    function groundShadow(box) {
        const size = box.getSize(new THREE.Vector3());
        const center = box.getCenter(new THREE.Vector3());
        // Torso-wide, not arm-wide (the long sleeve spreads its arms).
        const w = Math.min(size.x, HEIGHT * 0.6);
        const mesh = new THREE.Mesh(
            new THREE.PlaneGeometry(1, 1),
            new THREE.MeshBasicMaterial({ map: shadowTexture, transparent: true, depthWrite: false })
        );
        mesh.rotation.x = -Math.PI / 2;
        mesh.scale.set(w, w * 0.45, 1);
        mesh.position.set(center.x, box.min.y - HEIGHT * 0.06, center.z);
        mesh.raycast = () => {};   // never picked by clicks or drags
        return mesh;
    }

    const camera = new THREE.PerspectiveCamera(30, 1, 0.05, 100);
    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.enablePan = false;
    controls.autoRotate = true;
    controls.autoRotateSpeed = 2;
    controls.minPolarAngle = Math.PI * 0.25;
    controls.maxPolarAngle = Math.PI * 0.75;
    controls.addEventListener('start', () => { setSpin(false); fly = null; });   // the user takes over

    // Models are Draco-compressed (see images/models/web); the decoder is the
    // one from the same three.js release, served from js/lib/three so the 3D
    // view works without a CDN.
    const draco = new DRACOLoader().setDecoderPath(new URL('./lib/three/examples/jsm/libs/draco/gltf/', import.meta.url).href);
    const loader = new GLTFLoader().setDRACOLoader(draco);
    const modelCache = new Map();            // src -> Promise<Object3D>
    const broken = new Set();                // srcs that failed to load
    const loadModel = (src) => {
        if (!modelCache.has(src)) {
            modelCache.set(src, loader.loadAsync(src).then(g => g.scene));
        }
        return modelCache.get(src);
    };

    // Per-garment artwork: [{front:{canvas,texture}, back, left, right}, ...]
    const art = [0, 1].map(() => Object.fromEntries([...SIDES, ...SLEEVES].map(s => {
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 2;
        const texture = new THREE.CanvasTexture(canvas);
        texture.colorSpace = THREE.SRGBColorSpace;
        texture.anisotropy = renderer.capabilities.getMaxAnisotropy();
        return [s, { canvas, texture }];
    })));

    let current = null;   // { key, group, garmentMaterials:[], decals:[], sleeveSpots:[], shadows:[] }
    let wanted  = null;   // last show() request, so a slow load can't win over a newer one
    let dirty   = true;
    let visible = true;
    let tween   = null;   // camera swing to front/back
    let fly     = null;   // camera move to a sleeve close-up and back
    let homeView = null;  // {y, dist} of the default framing
    let facing  = 'front';

    function setStatus(text) {
        statusEl.textContent = text || '';
        statusEl.style.display = text ? '' : 'none';
    }

    function setSpin(on) {
        controls.autoRotate = on;
        document.getElementById('spin3dBtn')?.classList.toggle('active', on);
    }

    function resize() {
        const w = container.clientWidth, h = container.clientHeight;
        if (!w || !h) return;
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
        dirty = true;
    }
    new ResizeObserver(resize).observe(container);
    new IntersectionObserver(([e]) => { visible = e.isIntersecting; }).observe(container);

    function meshesOf(root) {
        const list = [];
        root.traverse(o => { if (o.isMesh) list.push(o); });
        return list;
    }

    /**
     * DecalGeometry reads raw vertex positions, which for a rigged (skinned)
     * model are the bind pose, not the shape on screen. Bake the bones in.
     */
    function decalSource(m) {
        if (!m.isSkinnedMesh) return m;
        const geo = m.geometry.clone();
        const pos = geo.attributes.position;
        const baked = new Float32Array(pos.count * 3);
        const v = new THREE.Vector3();
        for (let i = 0; i < pos.count; i++) {
            v.fromBufferAttribute(pos, i);
            m.applyBoneTransform(i, v);
            v.toArray(baked, i * 3);
        }
        geo.setAttribute('position', new THREE.BufferAttribute(baked, 3));
        const proxy = new THREE.Mesh(geo);
        proxy.matrixWorld.copy(m.matrixWorld);
        return proxy;
    }

    /**
     * Drop decal triangles that face away from `dir` (the way the print is
     * seen from): the inside of the garment, a lining layer, or the opposite
     * panel. Without this the back print shows mirrored through the front of
     * a hoodie. Triangles more than `tol` behind `surface` (a point on the
     * outer surface) are also dropped: that is lining seen through an
     * opening, like a vest's V-neck, or the torso behind a sleeve.
     */
    function keepFacing(geo, dir, surface, tol) {
        const pos = geo.attributes.position, nor = geo.attributes.normal, uv = geo.attributes.uv;
        const keep = [];
        const n = new THREE.Vector3(), c = new THREE.Vector3(), v = new THREE.Vector3();
        for (let t = 0; t < pos.count; t += 3) {
            n.set(0, 0, 0);
            c.set(0, 0, 0);
            for (let j = 0; j < 3; j++) {
                n.add(v.fromBufferAttribute(nor, t + j));
                c.add(v.fromBufferAttribute(pos, t + j));
            }
            const depth = v.copy(surface).sub(c.divideScalar(3)).dot(dir);
            if (n.dot(dir) > 0.3 && depth < tol) keep.push(t);
        }
        if (keep.length * 3 === pos.count) return geo;
        const out = new THREE.BufferGeometry();
        [['position', pos, 3], ['normal', nor, 3], ['uv', uv, 2]].forEach(([name, attr, n]) => {
            const arr = new Float32Array(keep.length * 3 * n);
            keep.forEach((t, k) => {
                for (let j = 0; j < 3 * n; j++) arr[k * 3 * n + j] = attr.array[t * n + j];
            });
            out.setAttribute(name, new THREE.BufferAttribute(arr, n));
        });
        geo.dispose();
        return out;
    }

    /**
     * Where the artwork sits: fire a ray at the chest from the front and from
     * the back, and project each decal where it lands. The projection is only
     * as deep as the garment is thick there, so the front print never shows
     * through on the back. A ray that slips through an opening (a vest's
     * V-neck) falls back to the bounding-box face.
     */
    function buildDecals(garment, meshes, box, cfg, aspect, slot) {
        const size = box.getSize(new THREE.Vector3());
        const center = box.getCenter(new THREE.Vector3());
        const width = size.x * cfg.printW;
        const y = box.min.y + size.y * cfg.chestY;
        const ray = new THREE.Raycaster();
        // Print on fabric only, never on buttons.
        const sources = meshes.filter(m => !/button/i.test(m.material.name)).map(decalSource);

        // The outer surface of each side: rays across the print width, keeping
        // the outermost hit on a face turned toward the ray. Several rays
        // because the middle one can pass through an opening (a vest's V-neck)
        // and land on the lining.
        const hitZ = {};
        SIDES.forEach(side => {
            const dir = side === 'front' ? -1 : 1;
            let best = null;
            [0, -0.35, 0.35].forEach(fx => {
                ray.set(new THREE.Vector3(center.x + fx * width, y, center.z - dir * size.z * 2), new THREE.Vector3(0, 0, dir));
                const hit = ray.intersectObjects(meshes, false).find(h => h.face &&
                    h.face.normal.clone().transformDirection(h.object.matrixWorld).z * dir < 0);
                if (hit && (best === null || hit.point.z * -dir > best * -dir)) best = hit.point.z;
            });
            hitZ[side] = best !== null ? best : (side === 'front' ? box.max.z : box.min.z);
        });
        const thickness = Math.max(hitZ.front - hitZ.back, size.z * 0.1);
        const depth = thickness * 0.9;
        const decalSize = new THREE.Vector3(width, width * aspect, depth);

        const out = [];
        SIDES.forEach(side => {
            const pos = new THREE.Vector3(center.x, y, hitZ[side]);
            const orient = new THREE.Euler(0, side === 'front' ? 0 : Math.PI, 0);
            const mat = new THREE.MeshStandardMaterial({
                map: art[slot][side].texture,
                transparent: true,
                depthWrite: false,
                polygonOffset: true,
                polygonOffsetFactor: -4,
                roughness: 0.85,
            });
            const dir = new THREE.Vector3(0, 0, side === 'front' ? 1 : -1);
            sources.forEach(m => {
                const geo = keepFacing(new DecalGeometry(m, pos, orient, decalSize), dir, pos, thickness * 0.2);
                if (geo.attributes.position.count === 0) { geo.dispose(); return; }
                const decal = new THREE.Mesh(geo, mat);
                decal.renderOrder = 1;
                decal.userData.print = { slot, part: side };   // for printUV()
                garment.add(decal);   // garment has identity transform, geometry is in world space
                out.push(decal);
            });
        });
        sources.forEach(m => { if (!meshes.includes(m)) m.geometry.dispose(); });
        return out;
    }

    /**
     * Sleeve prints. cfg.sleeve = {x, y, w}: the print centre as fractions of
     * the model's width (out from the middle) and height (up from the
     * bottom), and the print width as a fraction of the model's width. A ray
     * from outside the sleeve, turned a little toward the front, finds its
     * outer surface; the decal is laid along the surface normal there.
     *
     * Also returns where each sleeve is, so a click on it can be recognised.
     */
    function buildSleeves(garment, meshes, box, cfg, slot) {
        const decals = [], spots = [];
        if (!cfg.sleeve) return { decals, spots };
        const size = box.getSize(new THREE.Vector3());
        const center = box.getCenter(new THREE.Vector3());
        const w = size.x * cfg.sleeve.w;
        const ray = new THREE.Raycaster();
        const sources = meshes.filter(m => !/button/i.test(m.material.name)).map(decalSource);
        const worldNormal = h => h.face.normal.clone().transformDirection(h.object.matrixWorld);

        SLEEVES.forEach(which => {
            const sx = which === 'left' ? 1 : -1;   // the model faces +Z, so its left is +X
            const target = new THREE.Vector3(center.x + sx * size.x * cfg.sleeve.x,
                                             box.min.y + size.y * cfg.sleeve.y, center.z);
            // cfg.sleeve.z: how far the print turns toward the front (spread arms need more).
            const out = new THREE.Vector3(sx, 0, cfg.sleeve.z ?? 0.6).normalize();
            ray.set(target.clone().addScaledVector(out, size.x * 2), out.clone().negate());
            const hit = ray.intersectObjects(meshes, false).find(h => h.face && worldNormal(h).dot(out) > 0);
            if (!hit) return;
            const normal = worldNormal(hit);
            const aim = new THREE.Object3D();   // +Z along the normal, artwork kept upright
            aim.position.copy(hit.point);
            aim.lookAt(hit.point.clone().add(normal));

            const mat = new THREE.MeshStandardMaterial({
                map: art[slot][which].texture,
                transparent: true,
                depthWrite: false,
                polygonOffset: true,
                polygonOffsetFactor: -4,
                roughness: 0.85,
            });
            const decalSize = new THREE.Vector3(w, w, w * 1.2);
            sources.forEach(m => {
                const geo = keepFacing(new DecalGeometry(m, hit.point, aim.rotation, decalSize), normal, hit.point, w * 0.5);
                if (geo.attributes.position.count === 0) { geo.dispose(); return; }
                const decal = new THREE.Mesh(geo, mat);
                decal.renderOrder = 1;
                decal.userData.print = { slot, part: which };   // for printUV()
                garment.add(decal);
                decals.push(decal);
            });
            spots.push({ slot, which, point: hit.point.clone(), normal, radius: w * 1.1 });
        });
        sources.forEach(m => { if (!meshes.includes(m)) m.geometry.dispose(); });
        return { decals, spots };
    }

    async function build(req) {
        const src = await loadModel(req.model.src);
        if (wanted !== req) return;   // a newer request superseded this one

        if (current) {
            scene.remove(current.group);
            current.decals.forEach(d => { d.geometry.dispose(); d.material.dispose(); });
            current.garmentMaterials.forEach(m => m.dispose());
            current.shadows.forEach(m => { m.geometry.dispose(); m.material.dispose(); });
        }

        const group = new THREE.Group();
        scene.add(group);
        const garmentMaterials = [];
        const decals = [];
        const sleeveSpots = [];
        const garmentBoxes = [];
        const n = req.count;

        // Normalise once: centre on the origin, scale to HEIGHT, face +Z.
        const proto = SkeletonUtils.clone(src);
        proto.rotation.y = THREE.MathUtils.degToRad(req.model.rotY || 0);
        proto.updateMatrixWorld(true);
        const box0 = new THREE.Box3().setFromObject(proto);
        const s = HEIGHT / box0.getSize(new THREE.Vector3()).y;
        const w0 = box0.getSize(new THREE.Vector3()).x * s;

        for (let i = 0; i < n; i++) {
            const garment = new THREE.Group();
            const inst = i === 0 ? proto : SkeletonUtils.clone(proto);
            const c = box0.getCenter(new THREE.Vector3());
            inst.scale.setScalar(s);
            inst.position.set(-c.x * s + (i - (n - 1) / 2) * (w0 + GAP), -c.y * s, -c.z * s);
            garment.add(inst);
            group.add(garment);
            group.updateMatrixWorld(true);

            const meshes = meshesOf(inst);
            meshes.forEach(m => {
                m.material = m.material.clone();
                if (/button/i.test(m.material.name)) return;
                // Matte fabric: some models ship glossy or metallic settings
                // that make dark colours look like leather.
                m.material.metalness = 0;
                m.material.metalnessMap = null;
                m.material.roughness = 0.9;
                m.material.roughnessMap = null;
                m.material.needsUpdate = true;
                garmentMaterials.push(m.material);
            });
            const box = new THREE.Box3().setFromObject(inst);
            garmentBoxes.push(box);
            decals.push(...buildDecals(garment, meshes, box, req.model, req.aspect, i));
            const sleeves = buildSleeves(garment, meshes, box, req.model, i);
            decals.push(...sleeves.decals);
            sleeveSpots.push(...sleeves.spots);
        }

        // Frame everything, looking at the front (measured before the shadows
        // are added, so they do not change the framing).
        const all = new THREE.Box3().setFromObject(group);
        const dim = all.getSize(new THREE.Vector3());
        const shadows = garmentBoxes.map(groundShadow);
        shadows.forEach(m => group.add(m));

        current = { key: req.key, group, garmentMaterials, decals, sleeveSpots, shadows };
        applyColor(req.color);
        tween = null;   // a swing started before the model existed has nothing to aim at
        fly = null;
        const frameW = req.model.frameW || 1;
        homeView = { dim, get y() { return dim.y * 0.08; }, get dist() { return frameDist(dim, frameW); } };
        const dist = homeView.dist;
        controls.target.set(0, 0, 0);
        camera.position.set(0, dim.y * 0.08, facing === 'back' ? -dist : dist);
        controls.minDistance = dist * 0.45;
        controls.maxDistance = dist * 1.8;
        controls.update();
        setStatus('');
        dirty = true;
    }

    // Camera distance that fits a model of size `dim` in the current viewport:
    // all of its height and `frameW` (a fraction) of its width.
    function frameDist(dim, frameW = 1) {
        const fov = THREE.MathUtils.degToRad(camera.fov);
        return Math.max(dim.y, dim.x * frameW / camera.aspect) / 2 / Math.tan(fov / 2) * 1.25 + dim.z / 2;
    }

    function applyColor(hex) {
        if (!current) return;
        current.garmentMaterials.forEach(m => m.color.set(hex));
        dirty = true;
    }

    // Fly in to look at `point` from along its surface `normal`, a little from
    // above, at `k` times the default viewing distance.
    function closeUp(point, normal, k) {
        setSpin(false);
        const dir = normal.clone();
        dir.y = Math.max(dir.y, 0) + 0.15;
        dir.normalize();
        controls.minDistance = homeView.dist * 0.15;   // allow the close-up; home() restores it
        flyTo(point.clone().addScaledVector(dir, homeView.dist * k), point.clone());
    }

    // Glide the camera (and what it looks at) to a new spot.
    function flyTo(pos, target) {
        tween = null;
        fly = { p0: camera.position.clone(), q0: controls.target.clone(), p1: pos, q1: target, t0: performance.now() };
        dirty = true;
    }

    // Swing the camera to the front or back, keeping height and distance.
    function face(side) {
        facing = side;
        setSpin(false);
        fly = null;
        const off = camera.position.clone().sub(controls.target);
        const r = Math.hypot(off.x, off.z);
        const from = Math.atan2(off.x, off.z);
        let to = side === 'back' ? Math.PI : 0;
        while (to - from > Math.PI) to -= 2 * Math.PI;
        while (from - to > Math.PI) to += 2 * Math.PI;
        tween = { from, to, r, y: off.y, t0: performance.now() };
    }

    // A click (not a drag) on a sleeve asks the page to open the sleeve editor.
    const pointer = new THREE.Vector2();
    const picker = new THREE.Raycaster();
    function sleeveAt(ev) {
        if (!current || !current.sleeveSpots.length) return null;
        const r = renderer.domElement.getBoundingClientRect();
        pointer.set((ev.clientX - r.left) / r.width * 2 - 1, -(ev.clientY - r.top) / r.height * 2 + 1);
        picker.setFromCamera(pointer, camera);
        const hit = picker.intersectObject(current.group, true)[0];
        if (!hit) return null;
        let best = null;
        current.sleeveSpots.forEach(s => {
            const d = s.point.distanceTo(hit.point);
            if (d < s.radius && (!best || d < best.d)) best = { d, s };
        });
        return best && best.s;
    }
    let down = null;
    renderer.domElement.addEventListener('pointerdown', ev => { down = { x: ev.clientX, y: ev.clientY, t: performance.now() }; });
    renderer.domElement.addEventListener('pointerup', ev => {
        const d = down;
        down = null;
        if (!d || Math.hypot(ev.clientX - d.x, ev.clientY - d.y) > 6 || performance.now() - d.t > 500) return;
        const s = sleeveAt(ev);
        if (s) window.dispatchEvent(new CustomEvent('design3d-sleeve', { detail: { slot: s.slot, which: s.which } }));
    });
    let hoverQueued = false;
    renderer.domElement.addEventListener('pointermove', ev => {
        if (ev.buttons || hoverQueued || ev.pointerType !== 'mouse') return;
        hoverQueued = true;
        requestAnimationFrame(() => {
            hoverQueued = false;
            renderer.domElement.style.cursor = sleeveAt(ev) ? 'pointer' : '';
        });
    });

    renderer.setAnimationLoop(() => {
        if (!visible || container.offsetParent === null) return;
        if (tween) {
            const k = Math.min(1, (performance.now() - tween.t0) / 450);
            const e = 1 - Math.pow(1 - k, 3);
            const a = tween.from + (tween.to - tween.from) * e;
            camera.position.set(controls.target.x + Math.sin(a) * tween.r,
                                controls.target.y + tween.y,
                                controls.target.z + Math.cos(a) * tween.r);
            if (k === 1) tween = null;
            dirty = true;
        }
        if (fly) {
            const k = Math.min(1, (performance.now() - fly.t0) / 550);
            const e = 1 - Math.pow(1 - k, 3);
            camera.position.lerpVectors(fly.p0, fly.p1, e);
            controls.target.lerpVectors(fly.q0, fly.q1, e);
            if (k === 1) fly = null;
            dirty = true;
        }
        if (controls.update() || dirty) {
            renderer.render(scene, camera);
            dirty = false;
        }
    });

    window.Design3D = {
        /**
         * model: {src, rotY, chestY, printW, sleeve, frameW?}; count: 1 or 2 garments;
         * aspect: print area height / width.
         */
        show(model, color, count, aspect) {
            const key = [model.src, count, aspect].join('|');
            if (current && current.key === key && (!wanted || wanted.key === key)) {
                applyColor(color);
                return;
            }
            if (wanted && wanted.key === key) { wanted.color = color; return; }
            wanted = { key, model, color, count, aspect };
            setStatus('Loading 3D model…');
            const req = wanted;
            build(req).catch(err => {
                console.error('3D preview failed', err);
                broken.add(model.src);
                if (wanted === req) wanted = null;
                setStatus('');
                // Tell the page, so it falls back to the flat preview.
                window.dispatchEvent(new Event('design3d-ready'));
            });
        },

        /**
         * Copy artwork (a canvas) onto garment `slot` (0 or 1). `side` is
         * 'front' or 'back', or a sleeve: 'left' or 'right'.
         */
        setArtwork(slot, side, source) {
            const a = art[slot][side];
            if (a.canvas.width !== source.width || a.canvas.height !== source.height) {
                a.canvas.width = source.width;
                a.canvas.height = source.height;
                a.texture.dispose();   // size changed: GPU texture must be re-allocated
            }
            const c = a.canvas.getContext('2d');
            c.clearRect(0, 0, a.canvas.width, a.canvas.height);
            c.drawImage(source, 0, 0);
            a.texture.needsUpdate = true;
            dirty = true;
        },

        /** False once `src` has failed to load; the page then uses the flat preview. */
        canShow(src) { return !broken.has(src); },

        face,
        toggleSpin() { setSpin(!controls.autoRotate); },

        /**
         * Where a screen point lands on one print area of garment `slot`, as
         * texture coordinates {u, v} (0..1, v up), or null when it misses it.
         * `part` is 'front', 'back', 'left' or 'right' (a sleeve). Lets the
         * page drag artwork on the 3D garment itself.
         */
        printUV(clientX, clientY, slot, part) {
            if (!current) return null;
            const r = renderer.domElement.getBoundingClientRect();
            pointer.set((clientX - r.left) / r.width * 2 - 1, -(clientY - r.top) / r.height * 2 + 1);
            picker.setFromCamera(pointer, camera);
            const hits = picker.intersectObject(current.group, true);
            if (!hits.length) return null;
            const hit = hits.find(h => h.object.userData.print &&
                h.object.userData.print.slot === slot && h.object.userData.print.part === part);
            // It must be the visible surface, not a print seen through the body.
            const tol = homeView ? homeView.dim.y * 0.01 : 0.02;
            if (!hit || !hit.uv || hit.distance - hits[0].distance > tol) return null;
            return { u: hit.uv.x, v: hit.uv.y };
        },

        /** Close-up of one sleeve of garment `slot`. False if it has none (yet). */
        focusSleeve(slot, which) {
            const s = current && current.sleeveSpots.find(x => x.slot === slot && x.which === which);
            if (!s || !homeView) return false;
            closeUp(s.point, s.normal, 0.3);
            return true;
        },

        /**
         * Close-up of the point at texture coordinates (u, v) of one print
         * area (`part` as in printUV). False when that print area is missing.
         */
        focusPrint(slot, part, u, v) {
            if (!current || !homeView) return false;
            let best = null;
            current.decals.forEach(d => {
                const p = d.userData.print;
                if (!p || p.slot !== slot || p.part !== part) return;
                const uv = d.geometry.attributes.uv;
                for (let i = 0; i < uv.count; i++) {
                    const e = (uv.getX(i) - u) ** 2 + (uv.getY(i) - v) ** 2;
                    if (!best || e < best.e) best = { e, geo: d.geometry, i };
                }
            });
            if (!best) return false;
            // Decal geometry is in world space (see buildDecals).
            const point = new THREE.Vector3().fromBufferAttribute(best.geo.attributes.position, best.i);
            const normal = new THREE.Vector3().fromBufferAttribute(best.geo.attributes.normal, best.i).normalize();
            closeUp(point, normal, 0.3);
            return true;
        },

        /**
         * Back to the default framing (after a close-up, or after the viewer
         * moved to a differently shaped box), facing `side` if given.
         */
        home(side) {
            if (side) facing = side;
            if (!homeView) return;
            resize();   // the box may have just changed shape; frame for the new one
            const dist = homeView.dist;
            controls.minDistance = dist * 0.45;
            controls.maxDistance = dist * 1.8;
            setSpin(false);
            flyTo(new THREE.Vector3(0, homeView.y, facing === 'back' ? -dist : dist), new THREE.Vector3());
        },
    };

    resize();
    window.dispatchEvent(new Event('design3d-ready'));
}
