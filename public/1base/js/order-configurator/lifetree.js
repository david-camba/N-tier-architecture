function processSlotChilds(childDef){
    //tengo que iterararlos todos y construirlos como si no tuviera hijos, EXCEPTO KHEEEE, el hijo sea una función. Ahí si tengo que gestionarlo distintivo        
    const renderedChildren = [];
    if(childDef.children){
        if (Array.isArray(childDef.children)) {
            let processChildren = childDef.children.flat();
            processChildren.forEach(child => 
            {
                if (typeof child === 'function') throw new Error(`el nodo "${childDef}" tiene varios hijos, no puede generar una lista dinámica.`);

                renderedChildren.push(processSlotChilds(child)); //procesamos al hijo y lo guardamos como hijos procesados
            })
        }
        else if(typeof childDef.children === 'function') {
            return h(childDef.type, childDef.props, childDef.children);
        }
    }
        
    return h(childDef.type, childDef.props, renderedChildren);
}

function renderSlotChild(childDef, slotContext){
    
    if (typeof childDef.type === 'function'){
        //renderizamos un hijo componente
        childDef.props.idKey = childDef.idKey;
        //Nos aseguramos de que el idKey dentro de las props exista y sea igual al valor fijado fuera por el, sino, el setProp no funcionaría. Esto resuelve el caso de que se sobreescriban las props de golpe de un target sin necesidad de repetir el idKey. Asegura consistencia.

        const renderedComponent = h(childDef.type, {...childDef.props, slotContext: slotContext}); //le pasamos las props como un nuevo objeto, y también el slotContext para que lo tenga asignado desde su construcción. Esto nos ayudará a encadenar slots.
        
        //renderedComponent.inList = true; //no está en lista
        renderedComponent.idKey = renderedComponent.props.idKey; //sacamos su idKey al nodo general para que sea más accesible
        return renderedComponent;
    } 
    else if(childDef.type === 'slot'){
        //renderizamos un slot aninado dentro de otro slot
        childDef.props.idKey = childDef.idKey;
        const renderedSlot = h(childDef.type, {...childDef.props, slotContext: slotContext});
        return renderedSlot;
    }
    else{
        //un nodo normal: puede ser una isla estática o un nodo que contenga una lista dinámica
        //puede tener hijos
        if(childDef.idKey) childDef.props.idKey = childDef.idKey;
        const renderedNode = processSlotChilds(childDef);
        renderedNode.slotContext = slotContext;
        return renderedNode;
    }

}

function setSlotDependencies(dependsOn, childVNode){
    //Si es slot, necesitamos solicitar todas sus dependencias para poder actualizarlo
    if(childVNode.isSlot){
        for (const dependency of Object.keys(childVNode.dependsOn)) {
            dependsOn[dependency] = dependency;
        }                   
    }

    //Si es manager de lista o camino a una lista, tambien lo necesitamos
    if (childVNode.managerOf) {
        dependsOn[childVNode.managerOf] = childVNode.managerOf; 
    }
    if(childVNode.dynamicLists){
        for (const listName of childVNode.dynamicLists) {
            dependsOn[listName] = listName;
        }
    }

    //Si el hijo es un componente y tiene fijado un "defMap" para nutrirse y modificar propiedades del estado global, también necesitamos esta dependencia
    if(childVNode.isComponent && childVNode.props.updateMap){
        Object.keys(childVNode.props.updateMap).forEach(key => {
            dependsOn[key] = key;
        });
    }
}

/**
 * Crea una descripción de un nodo de la UI (un VNode).
 * Determina si el nodo es estático o dinámico para futuras optimizaciones.
 * @param {string|Function} type - El tipo de elemento ('div', 'p') o un Componente (una función).
 * @param {object} props - Las propiedades del nodo. Las props dinámicas deben empezar con '$'.
 * @param  {...any} children - Los nodos hijos.
 */
// TO-DO: Esta función necesita un gran refactor. Ahora lo hace todo y tiene toda la lógica de construcción del vTree dentro. Sería preferible que funcionara como un dispatcher de acciones y los diferentes casos: nodo normal, componente, slot, pertenencia a una lista dinámica, etc fueran otras funciones

export function h(type, props, ...children) {
    props = props || {}; // Asegurarnos de que props nunca sea null
    
    if(type === 'slot'){
        const slotChildren = [];
        const childrenDef = props.childrenDef.flat();
        const slotName = props.slotName;
        const slotContext = (props.slotContext) ? [...props.slotContext, slotName] : [slotName]; //si algún slot padre nos ha proporcionado slotContext, añadimos el del propio slot al final. Sino, somos el primer padre slot de la rama y añadimos el nombre sin más al array.
        const domProps = props.domProps; //las propiedades que se pondran en el nodo al crear el slot

        const dependsOn = {};
        dependsOn[slotName] = slotName;    

        //NOTA: Hay que integrar al slot en el flujo de dynamic lists normal y corriente, como si fuera un componente más

        childrenDef.forEach((childDef, index) => {    
            const renderedChild = renderSlotChild(childDef, slotContext);
            slotChildren.push(renderedChild);
        });

        // Recorremos los hijos procesados para ver que cambios necesitamos estar observando
        slotChildren.forEach(child => { 
            setSlotDependencies(dependsOn,child);
        }); 

        return {
            type: 'slot', // ??? Guardamos la función del componente por si hiciera falta un renderizado???
            slotName: slotName,
            slotContext: slotContext,
            isComponent: true,
            isSlot: true,
            props: childrenDef,
            children: slotChildren,
            isDynamic: true, 
            dom: null,
            dependsOn: dependsOn, //para que los padres que no sean componentes transfieran correctamente
            domProps: domProps,
        };
    }

    let processedChildren = children.flat(); 

    //Si type es una función, es un componente. Lo ejecutamos para que procese a sus hijos.
    if (typeof type === 'function') processedChildren = [type(props)];       

    //let isManager = false; //comprobamos si estamos ante un node manager que guardará como renderizar a sus hijos
    let managerOf = null; //nombre de la lista que maneja
    let renderChildren = null; //la función para renderizar a sus hijos
    const dynamicLists = new Set(); //array de listas dinámicas hijas del nodo

    if (typeof processedChildren[0] === 'function') {
        if (processedChildren.length > 1) {
            throw new Error(`h(): el nodo "${type}" con props "${props}" es una lista mezclada con otros hijos, esto no esta permitido`);
        }
        //isManager = true; //fijamos al nodo como manager de otros nodos

        // NOTA DE ARQUITECTURA - este nodo servirá de referencia para que el componente ordene a sus hijos si detecta algún cambio en la lista.     
        // ¿Por qué lo hace el componente? Porque en vez de renderizar todo, lo que vamos a hacer es una mutación temporal de la lista para solo renderizar los nuevos nodos, sin necesdidad de renderizar la lista entera. Nos mantenemos quirurjicos. Y el que tiene acceso al closure que renderiza a los hijos es el componente.
        // Pero, el que tiene acceso a la función de renderizado, es el nodeManager.
        // El componente utilizará la función de renderizado y al nodeManager como referencia para realizar la ordenación de la lista si es necesario

        renderChildren = processedChildren[0]; //guardamos la función de renderizado de hijos dinámicos, el componente la buscará y la lanzará para realizar renderizados parciales 

        // Obtenemos con el compilador JIT la propiedad de la lista.
        const listProp = compileDependencies(renderChildren);
        /* Sintaxis para generar listas dinámicas
            1- Dentro de un componente (o directamente en el componente App) - ARROW FUNCTION
                () => props.$tasksMap.map(task => h('li'...) //
            2- Directamente dentro de la definición de un slot, sin estar dentro de ningún componente - ANON FUNCTION
                function() { return this.$tasksMap.map(task => h('li'...)
                                    
            Este "$tasksMap" servirá de referencia al componente/nodeManager para gestionar el renderizado.
            Es importante seguir la convención porque para el renderizado se utiliza el contexto "this" dentro de un slot para que el nodeManager funcione como renderizador y punto de referencia, y en el componente el componente funcionará como renderizador y el node manager solo será un punto de referencia. Se juega con los closures para permitir estos dos casos de uso dentro de la misma arquitectura.
        */
        managerOf = listProp[0]; //nombre de referencia para que el componente sepa desde donde mover la lista y donde lanzar la fucnión de renderizado 

        if (renderChildren.prototype){
            //Nos sirve para detecta si usamos una arrow funcion o no
            //si estamos usando una función anonima normal, es que estamos definiendo una lista dentro de un slot. Tenemos que bindear las propiedades del nodo porque será el encargado de re-renderizar la lista. Le bindeamos las props que deben contener la lista que queremos renderizar
            // NOTA: si estamos usando una función flecha, es que la lista está dentro de un componente y será este el encargado de realizar las mutaciones temporarles quirúrjicas. No se usa el this.

            //Tenemos que fijar la lista a manejar dentro de propiedades. Hemos encontrado el nombre dinámicamente con el compilador JIT
            let slotContext = state;
            if(props.slotContext){
                for (const slot of props.slotContext) {
                    slotContext = slotContext[slot];
                }  
            }

            //Si existe la lista dinámica porque la hemos fijado dentro del objeto slot, la sacamos de ahí. Sino, se usa la del primer nivel de state
            //NOTA DE ARQUITECTURA: esta posibilidad dual es probablemente innecesaria. Hay que pulir el manejo de arrays que mezclen indices númericos con strings y además anida los datos de formas más profunda. El objetivo de esta funcionalidad es poder crear rapidamente una lista en cualquier slot sin necesidad de utilizar un componente, por lo que esta complicación puede ser innecesaria. Siempre se puede crear un componente lista sencillo para utilizar cuando sea necesario de forma normal, la gracia de esta solución es poder utilizar listas normales de el primer nivel dentro de slots, pero implica una refactorización problablemente innecesaria en el uso real
            //props[managerOf] = (slotContext[managerOf]) ? slotContext[managerOf] : state[managerOf];

            props[managerOf] = state[managerOf]; //OVERRIDE: por el momento nos quedaremos con esto

            if (!state[managerOf]) {
                throw new Error(`La lista ${managerOf} no existe en el primer nivel de estado`);
            }

            renderChildren = processedChildren[0].bind(props); //bindeamos las props del nodo al contexto para poder renderizar la lista y que el node manager se encargue a partir de ahora de re-renderizar y ordenar la lista
        }

        //AMPLIACIÓN EN TESTING: Para dar más flexibilidad a la hora de construir los condicionales y la lógica para renderizar hijos, procesamos el resultado y lanzamos warnings para avisar
        processedChildren = renderChildren(); //renderizamos los hijos
        if (Array.isArray(processedChildren)) {
            // Caso 1: existe y es un array → lo aplanamos
            processedChildren = processedChildren.flat();
        } else if (processedChildren != null) {
            // Caso 2: existe pero no es array → lo convertimos en array de un solo elemento y lanzamos warn
            console.warn(`La lista ${managerOf} no ha devuelto un array`, children.toString());
            processedChildren = [processedChildren];
        } else {
            // Caso 3: no existe → array vacío y lanzamos warn
            console.warn(`La lista ${managerOf} está vacía`, children.toString());
            processedChildren = [];
        }

        dynamicLists.add(managerOf); //esta dato viajará hasta el componente para que sepa cuáles de sus propiedades son listas y las maneje apropiadamente            
        
        processedChildren.forEach(child => {
            if (child && typeof child === 'object') {
                child.isDynamic = true; //fijamos a todos los hijos como dinámicos
                child.props.inList = managerOf; //y les marcamos una propiedad con la lista a la que pertecen para que "setProp" actualice el estado global correctamente
            }
        }); 



    } else {
        if (processedChildren.some(child => typeof child === 'function')) {
            throw new Error(`h(): el nodo "${type}" con props "${props}" los hijos normales no pueden ser funciones`);
        }
    }

    // --- 1. Procesamiento de Props: ¿Tiene este nodo props dinámicas? ---
    let hasDynamicProps = false;
    const DynamicProps = {};

    // Analizamos si alguna propiedad es dinámica.
    for (const key in props) {
        if (key.startsWith('$')) {
            DynamicProps[key] = false; //Lo ponemos "false" porque luego se incluirá en "dependsOn" para avisar a los padres de que necesita esta propiedad y que se la tienen que transmitir si la tienen, pero no va a necesitar la ejecución de ninguna otra propiedad. Simplemente se usa como etiqueta de referencia, para avisar a los carteros.
            hasDynamicProps = true;
        }
    }


    // LA REGLA DE LA COMPUERTA: un componente solo dependerá de sus propiedades dinámicas, no le interesa lo que necesiten sus hijos, son sus hijos los que tienen que pedirle a él datos
    let dependsOn = {};
    let hasDynamicChildren =  false;
    if (typeof type !== 'function'){

        // COMPILADOR JIT
        // Nos permite obtener las dependencias internas de funciones
        // ¿A qué llamamos dependencias? A propiedades heredadas directamete del componente y formateadas con el uso de funciones
        // innerText: () => `El contador actual es: ${props.$count}`
        // Este compilador obtiene las referencia "$count" y la asocia a la propiedad que contiene su función 
        // Esto será posteriromente utilizado por nuestro "Fiber Tree" para ejecutar y compartir la propiedad con los hijos si esta ha cambiado o para podar la rama
        dependsOn = buildDependencyMap(props);
        
        //Comprobamos si alguno de los hijos nos "informa" de que es dinámico a traves sus propiedades.
        if(!hasDynamicProps){
            hasDynamicChildren = processedChildren.some(child => child && child.isDynamic);
        }        
    }

    // Recorremos processedChildren para obtener sus dependencias
    processedChildren.forEach(child => { 
        // añado las listas si no están duplicadas. 
        // nota: no añado las listas de componentes, las listas solo suben hasta su componente padre y no avanzan más, para que sea el componente padre el que gestiona la posición de sus nodos
        if (!child.isComponent && child.dynamicLists && child.dynamicLists.size > 0) {
            for (const listName of child.dynamicLists) {
                // Y lo añadimos al Set principal.
                // .add() se encargará de ignorarlo si ya existe.
                dynamicLists.add(listName);
            }
        }

        if (typeof type !== 'function'){ 
            //si uno de sus hijos es manager, este nodo es un camino a una lista
            /*if(child?.isManager){
                isManager = true;
            }*/
            if (child?.dependsOn) {
                Object.keys(child.dependsOn).forEach(key => {
                    // Si no está la referencia, la establecemos para que fucnione solo como cartero. Si ya está, no podemos pisar el valor anterior porque nos quedaríamos sin mapa.
                    if (!(key in dependsOn)) {
                        // No traemos el valor de la función a la que apunta el hijo, solamente la referencia de que necesita esta data. Así hacemos a este nodo funcionar como CARTERO. 
                        dependsOn[key] = false; //Dijamos false para que se comporte solo como cartero
                    }
                });
            }
        }
    });      

    // --- 2. Decidimos la lógica según el tipo de nodo ---
    // ES COMPONENTE
    if (typeof type === 'function') {
        // --- CASO COMPONENTE: Actúa como una "compuerta" ---
        // Su dinamismo SÓLO depende de sus propias props. No mira a sus hijos.

        //Nombramos a los componentes para debugear más fácilmente
        const componentName = type.name;
        props.componentName = componentName;

        return {
            type: 'component', // ??? Guardamos la función del componente por si hiciera falta un renderizado???
            name: type.name,
            isComponent: true,
            props: props,
            children: processedChildren,
            isDynamic: hasDynamicProps, // La regla de la compuerta
            dom: null,
            dependsOn: DynamicProps, //para que los padres que no sean componentes transfieran correctamente
            renderChildren: renderChildren,
            //isManager: false, //fijamos como false "isManager" para que otros componentes no entren a modificar las listas internas. Se encargará el componente mismo cuando se actualicen sus props
            managerOf: managerOf, //mantiene su nombre, porque lo primero que hará será comprobar si el mismo es "managerOf" de la lista con la que quiere trabajar
            dynamicLists: dynamicLists,
            idKey: props?.idKey,
            slotContext: props.slotContext,
        };

    } else {
        const hasDynamicDependencies = Object.keys(dependsOn).length > 0; //si tiene dependencias dinámicas, es dinámico
        // --- CASO ELEMENTO HTML ('div', 'p', etc.): Propaga el dinamismo ---
        // Es dinámico si sus props son dinámicas O si alguno de sus hijos es dinámico.
        return {
            type: type, // El string 'div', 'p', etc.
            isComponent: false,
            props: props,
            children: processedChildren,
            isDynamic: hasDynamicProps || hasDynamicChildren || hasDynamicDependencies, // La regla de propagación
            dependsOn: { ...DynamicProps, ...dependsOn },
            dom: null,
            renderChildren: renderChildren,
            //isManager: isManager,
            managerOf: managerOf,
            dynamicLists: dynamicLists,
            idKey: props?.idKey,
            slotContext: props.slotContext,
        };
    }
}

/**
 * Analiza las props de un VNode y construye un mapa de dependencias.
 * El mapa es un índice que asocia una clave de dependencia (ej. '$isActive')
 * con un array de los NOMBRES de las props que la utilizan.
 * @param {object} props - El objeto de props a analizar.
 * @returns {object} Un objeto que funciona como un mapa-índice de dependencias.
 */
function buildDependencyMap(props) {
    const dependencyMap = {};

    for (const propName in props) {
        
        if (propName.startsWith("on")) continue; //Si es un evento (onclick, onhover...) no compilamos sus dependencias, esos valores vendrán definidos desde fuera y no es necesario que se activen cuando suceda un cambio.

        const propValue = props[propName];

        if (typeof propValue === 'function') {
            // 1. Compilamos las dependencias de la función de esta prop.
            const dependencies = compileDependencies(propValue);

            // 2. Por cada dependencia encontrada...
            dependencies.forEach(dep => {
                // ...registramos que `propName` depende de `dep`.
                
                if (!dependencyMap[dep]) {
                    dependencyMap[dep] = [];
                }

                // Añadimos el nombre de la prop al array de la dependencia,
                // asegurándonos de no duplicarlo.
                if (!dependencyMap[dep].includes(propName)) {
                    dependencyMap[dep].push(propName);
                }
            });
        }
    }        
    return dependencyMap;
}

/**
 * Analiza el código fuente de una función para extraer dependencias
 * que sigan el patrón `props.$variable`.
 * @param {Function} fn - La función computada a analizar.
 * @returns {string[]} Un array de strings con los nombres de las dependencias encontradas (ej. ['$isActive', '$user']).
 */
function compileDependencies(fn) {
    if (typeof fn !== 'function') {
        return [];
    }

    const fnAsString = fn.toString();

    // --- La Expresión Regular ---
    // Explicación:
    // 1. `props\.`: Busca la cadena literal "props." (el \ escapa el punto, que es un carácter especial en regex).
    // 2. `\$`: Busca el carácter literal "$".
    // 3. `([a-zA-Z0-9_]+)`: Este es el grupo de captura.
    //    - `[]`: Define un conjunto de caracteres permitidos.
    //    - `a-zA-Z0-9_`: Letras (mayúsculas y minúsculas), números y guion bajo. Los caracteres válidos para nombres de variables en JS.
    //    - `+`: Busca una o más ocurrencias de los caracteres anteriores.
    // 4. `g`: Flag global. Le dice a la regex que no se detenga en la primera coincidencia, sino que busque todas las que haya en el string.

    //const dependencyRegex = /props\.\$([a-zA-Z0-9_]+)/g;

    const dependencyRegex = /\$([a-zA-Z0-9_]+)/g; //compilado más agresivo, por ".$" para encontrar variables en listas dinámicas

    // `matchAll` es perfecto para esto, ya que devuelve un iterador con todas las coincidencias
    // y sus grupos de captura.
    const matches = fnAsString.matchAll(dependencyRegex);

    const dependencies = new Set(); // Usamos un Set para evitar duplicados automáticamente.

    for (const match of matches) {
        // El grupo de captura 0 es la coincidencia completa (ej. "props.$isActive").
        // El grupo de captura 1 es lo que está dentro de los paréntesis `()` en la regex.
        // Añadimos el '$' de vuelta para mantener la consistencia en el nombre de la dependencia.
        dependencies.add('$' + match[1]);
    }

    // Convertimos el Set de nuevo a un Array.
    return Array.from(dependencies);
}



// 4. EL MOTOR DE RENDERIZADO - De VNode a DOM Real
// Esta función toma un VNode y lo convierte en un elemento real del DOM.
// Convertiremos nuestros "plantilla nodo" y sus hijos en un nodo real gracias a esta función recursiva
export function createDomNode(vNode, parentVNode=null) {
    // Si el "nodo" es solo texto, creamos un nodo de texto, sin etiquetas.
    // Esto nos permite pasar el texto a incluir en nuestro nodo principal en nuestro nodo plantilla como:
    // h('p', null, 'Esto es un parrafo'), y el texto se comportará como un nodo más manejado por la llamada recursiva
    if (typeof vNode === 'string' || typeof vNode === 'number') {
        throw new TypeError("A VNode cannot be a string");
    }

    const domNode = (vNode.type === 'slot') ? document.createElement('slot-container') : document.createElement(vNode.type);

    // Si el nodo es dinámico o lo es su padre, le asignamos un puntero para poder manejarlo
    // Así evitamos asignar punteros a "islas estáticas" que nunca van a cambiar
    // Pero, a la vez mantenemos la referencia de toda la isla para poder moverla y realizar otras operaciones
    if(vNode.isDynamic || parentVNode?.isDynamic) vNode.dom = domNode;

    // Lanzaremos el "onMount" cuando el componente esté completamente renderizado, de momento lo guardamos en una cola
    if(vNode.isComponent && typeof vNode.props?.onMount === "function"){
        mountQueue.push(() => vNode.props.onMount(vNode));
    }   
    
    // Si es un slot, debemos definidir las propiedades
    if(vNode.isSlot){
        const customProps = {
            slotName : vNode.slotName, 
            idKey: vNode.idKey, 
            ...vNode.domProps,
        }
        patchDomNode(domNode, customProps);
    } 
    else{
        // Le asignamos las propiedades (atributos, eventos y etiquetas) definidas  
        patchDomNode(domNode, vNode.props); 
    }

    // Hacemos lo mismo recursivamente para los hijos.
    if (vNode.children){
        vNode.children.forEach(childVNode => {
            const childDomNode = createDomNode(childVNode, vNode);
            domNode.appendChild(childDomNode);
        });
    }

    return domNode;
}

// 3. Función Auxiliar para Actualizar Propiedades (Atributos y Eventos)
// Compara las propiedades antiguas y nuevas para añadir, eliminar o modificar lo necesario.
function patchDomNode(target, changes) {    
    if(!changes) {
        console.error("Se esperaba un objeto changes, para hacer update al nodo: ",target); 
        return;
    }

    // Si es un componente, fijamos su nombre, su clase, id y estilo y nada más
    // Si es un componente, solo sincronizamos un conjunto específico de atributos clave.
    if (changes.componentName) {
        target.setAttribute('data-name', changes.componentName);
        if (debugOn && changes.idKey) {
            target.setAttribute('data-idkey', changes.idKey);
        }
        /*for (const attr of componentAttributes) {
            // Verificamos si el atributo existe en 'changes' y tiene un valor.
            // Usamos 'hasOwnProperty' o 'in' para ser explícitos.
            if (changes.hasOwnProperty(attr)) {
                // Si existe, lo aplicamos al elemento DOM.
                target.setAttribute(attr, changes[attr]);
            }
        }
        return;*/
    }
    // Lista de atributos que un componente puede declarar directamente.
    const componentAttributes = ['id', 'class', 'style'];

    // Quitamos los listeners que nos hayan indicado que se deben quitar
    if(changes.removeListeners){
        changes.removeListeners.forEach(listener => {
            target.removeEventListener(listener.eventName, listener.callback);
        });
        //lo borramos, ya está procesado
        delete changes.removeListeners;
    }

    // Añadir/modificar nuevas propiedades
    for (let name in changes) {  
        //saltamos las propiedades de debug
        const debugProps = ['idKey', 'inSlot', 'slotName'];     
        if(!debugOn && debugProps.includes(name)) continue;
        
        if (changes.componentName && !componentAttributes.includes(name) && !name.startsWith('on')) continue;

        //saltamos la animación, se gestionará al final
        if(name === 'animation') continue;       

        let cleanName = name.startsWith('$') ? name.substring(1) : name;
        const value = changes[name];

        //Añadimos eventos
        if (cleanName.startsWith('on')) {
            const eventName = cleanName.substring(2).toLowerCase();
            target.addEventListener(eventName, value);

        // Añadimos atributos
        } else {
            // Si es una función, ejecutamos para obtener el valor
            const finalValue = (typeof value === "function") ? value() : value;  

            //Set/remove atributos (podemos usar null para quitar una propiedad)
            if(finalValue){
                //ponemos "data-" delante de los datos de debug
                if(debugProps.includes(cleanName)){
                    cleanName = 'data-'+cleanName;
                }

                //si es nuestro especial manejador de texto, 
                if(cleanName === 'innerText'){
                    if (!target.style.whiteSpace) { //comprueba si no hay un "whiteSpace" fijado
                        target.style.whiteSpace = 'pre-wrap'; //respeta el formato original representado en el string "innerText" y lo ajusta al contenedor si es necesario
                    }
                    target.textContent = finalValue;
                    continue;
                }
                
                // TO-DO: admitir también objetos o forzar a objetos y realizar comparación. De momento esto es funcional.
                else if (cleanName === 'style') {
                    // Para "style", parseamos la cadena que viene y añadimos los valores que vengan, y si alguno ya estaba, lo actualizamos.
                    // TO-DO: admitir "color:!remove" o algo así para poder borrar un estilo determinado, ahora solo se puede borrar todo o actualizar
                    const parse = s => Object.fromEntries(s.split(';').map(x => x.split(':').map(v => v?.trim())).filter(x => x[0]));
                    const merged = { ...parse(target.getAttribute('style') || ''), ...parse(finalValue) };
                    target.setAttribute('style', Object.entries(merged).map(([k, v]) => `${k}:${v}`).join('; '));
                }
                else if(cleanName === 'setAttr'){
                    for (const key in finalValue) {
                        target[key] = finalValue[key]; //fijamos todos los atributos del objeto que hayamos pasado
                    }                    
                }
                //El caso normal
                else {
                    target.setAttribute(cleanName, finalValue);
                }                    
            }
            else{
                target.removeAttribute(cleanName);
            }
        }            
    }

    //se aplica la animación de cambio (si se ha pasado)
    if(changes['animation']){
        applyAnimation(target, changes['animation']);
    }
}

/**
 * Actualiza un VNode en memoria con nuevas propiedades y prepara un "changeset" para el DOM.
 * Su principal responsabilidad es identificar los event listeners que necesitan ser reemplazados
 * para instruir a `patchDomNode` que los elimine antes de añadir los nuevos.
 *
 * @param {object} vNode - El nodo virtual que se va a mutar.
 * @param {Array<object>} propsToUpdate - Un array de objetos, cada uno con { key, newValue }.
 * @returns {object} Un objeto que contiene las propiedades a cambiar en el DOM real,
 *                   incluyendo una instrucción especial `removeListeners` si es necesario.
 */
function updateVNode(vNode, propsToUpdate) {
    // 1. Objeto que contendrá las instrucciones para `patchDomNode`.
    const changesForDOM = {};
    const listenersToRemove = [];

    // 2. Iteramos sobre cada cambio que necesitamos aplicar.
    for (const [key, newValue] of Object.entries(propsToUpdate)) {
        const cleanKey = key.startsWith('$') ? key.substring(1) : key;

        // 3. Lógica para detectar el reemplazo de un event listener.
        if (cleanKey.startsWith('on') && typeof vNode.props[key] === 'function') {
            // Si la nueva prop es un evento (ej. '$onclick') y ya existía una función
            // para ese evento en el VNode, significa que la estamos reemplazando.
            
            // Preparamos la instrucción para eliminar el listener antiguo del DOM.
            listenersToRemove.push({
                eventName: cleanKey.substring(2).toLowerCase(),
                callback: vNode.props[key] // Guardamos la referencia a la función ANTIGUA.
            });
        }
        
        // 4. Actualizamos (mutamos) el VNode con el nuevo valor.
        // Esto es crucial para que en el siguiente ciclo de updates, la comparación se haga
        // contra el estado más reciente.
        vNode.props[key] = newValue;

        // 5. Añadimos el cambio al objeto de instrucciones que devolveremos.
        changesForDOM[key] = newValue;
    }

    // 6. Si hemos acumulado listeners para eliminar, los añadimos a las instrucciones.
    if (listenersToRemove.length > 0) {
        changesForDOM.removeListeners = listenersToRemove;
    }

    // 7. Devolvemos el conjunto de cambios listos para ser aplicados al DOM real.
    return changesForDOM;
}


/* 
Si alguna propiedad del nodo hijo está asociada con algún cambio de propiedades en el compotente, ejecuta su callback asociado para actualizar esa propiedad. 
*/
function calculateDependantProps(vNode, propsToExecute) {
    const propsToUpdate = {};
    let skipTextProp = false;

    Object.keys(propsToExecute).forEach(prop => {
        if(vNode.dependsOn[prop]){
            const propExecutions = vNode.dependsOn[prop];    

            propExecutions.forEach(propName => {
                const propFunction = vNode.props[propName];

                // Si es función y no se ha computado, ejecutamos y guardamos su resultado
                if (!propsToUpdate[propName] && (typeof propFunction === "function")){   
                    const isTextProp = ['$innerText', 'innerText'].includes(propName);
                    if(isTextProp && skipTextProp) return; //si es textProp y ya hemos comprobado que no queremos actualizarlo, saltamos

                    const computedValue = propFunction();
                    
                    // Si es innerText y no ha cambiado, saltamos para evitar el re-render
                    if (isTextProp && computedValue === vNode.dom.textContent){
                        skipTextProp = true;
                        return;
                    } 
                    // Si es innerText y cambia, agregamos animación
                    if (isTextProp) {
                        propsToUpdate['animation'] = vNode.props?.updateAnimation ?? defaultUpdateAnimation;
                    }

                    propsToUpdate[propName] = computedValue;
                }                
            });
        }
    });

    // devolvemos el resultado de los cambios que deben realizarse en el DOM
    return propsToUpdate;
}

function findManagerNode(vNode, managerToFind){
    //si es el nodo manager, lo devolvemos
    if (vNode.managerOf == managerToFind) return vNode;

    //si no, recorremos los hijos y buscamos aquel que contenga el camino al manager
    for (const child of vNode.children) {
        if (child.dynamicLists?.has(managerToFind)){
            return findManagerNode(child, managerToFind);
        } 
    };

    throw new Error(`No se pudo encontrar el nodo manager de la lista "${managerToFind}"`);
}

function findAllManagerNodes(vNode, managerToFind) {
    const results = [];

    // Si este nodo es un manager del tipo buscado, lo añadimos
    if (vNode.managerOf == managerToFind) {
        results.push(vNode);
    }

    // Recorremos los hijos y acumulamos los resultados
    for (const child of vNode.children) {
        if (child.dynamicLists?.has(managerToFind)) {
            results.push(...findAllManagerNodes(child, managerToFind));
        }
    }

    return results;
}

//Destruye un nodo del DOM. Si es componente, lanza sus hooks de unmount
function destroyDOMNode(VNodeToDestroy, replacementDomNode=null){
    //lanzamos el hook "beforeUnmount" del componente
    if (VNodeToDestroy.isComponent && typeof VNodeToDestroy.props?.beforeUnmount === "function"){
        VNodeToDestroy.props.beforeUnmount(VNodeToDestroy);
    }
    if(!replacementDomNode){
        //Si no hay replacement, tenemos que lanzar la animación unmount y no retirar el elemento del DOM hasta que termine. Esto se puede lograr con un event listener.

        //Lanzamos su unMountAnimation
        const unmountAnimation = getAnimation(VNodeToDestroy, 'unmount');
        applyAnimation(VNodeToDestroy.dom, unmountAnimation);

        // Hacemos las copias necesarias del nodo para que su afterUnmount se ejecute correctamente 
        // (asi nos aseguramos que aunque el nodo virtual del arbol sea destruido, podemos ejecutar la funcion correctamente)
        const VNodeCopy = {...VNodeToDestroy}
        let afterUnmountFunction = null;            
        if (VNodeCopy.isComponent && typeof VNodeCopy.props?.afterUnmount === "function"){
            afterUnmountFunction = VNodeCopy.props.afterUnmount;
        }

        //creamos el evento para que una vez acabada la transición, se borre
        VNodeToDestroy.dom.addEventListener('transitionend', () => {
            VNodeCopy.dom.remove(); //borramos el elemento del DOM
            if(afterUnmountFunction) afterUnmountFunction(VNodeCopy); //ejecutamos la función que teníamos lista
        }, {once: true} ); //con esto conseguimos que el evento se borre solo después de lanzado                
    }
    else{
        VNodeToDestroy.dom.replaceWith(replacementDomNode); //lo sustituimos en el DOM
        //lanzamos el hook "afterUnmount" del componente
        if (VNodeToDestroy.isComponent && typeof VNodeToDestroy.props?.afterUnmount === "function"){
            VNodeToDestroy.props.afterUnmount(VNodeToDestroy);
        }
    }       


}


function organizeDynamicList(managerNode, oldList, newList, component, listName){

    const newIdKeys = new Set(newList.map(item => item.idKey)); //set con las nuevas idKeys, si alguna no está, la crearemos

    let currentOrderIdKeys = oldList.map(item => item.idKey); //Array con el orden actual de las keys, lo manipularemos a medida que vayamos haciendo actualizaciones para que siempre represente el estado 

    // 3b. PASADA 1: PODA. Eliminamos nodos del vTree y del DOM real
    // Iteramos hacia atrás para no afectar los índices de los elementos pendientes.
    for (let i = currentOrderIdKeys.length - 1; i >= 0; i--) {                
        const idKey = currentOrderIdKeys[i];
        if (!newIdKeys.has(idKey)) {  

            //Lo buscamos en en los hijos del vTree para eliminarlo
            for (let i = 0; i < managerNode.children.length; i++) {
                if (managerNode.children[i].idKey === idKey) {

                    const VNodeToDestroy = managerNode.children[i];

                    destroyDOMNode(VNodeToDestroy); //lo eliminamos del DOM y se gestiona su unMount

                    managerNode.children.splice(i, 1); //lo eliminamos del vTree
                    break; 
                }
            }
            // Eliminamos la key para que nuestro "currentOrderIdKeys" se mantenga actualizado
            currentOrderIdKeys.splice(i, 1);
        }
    }       
    

    // 3c. PASADA 2: REORDENAMIENTO E INSERCIÓN
    // Añadiremos en la posición del estado los nodos que no existan y re-ordenaremos los que hayan cambiado de posición   

    //Preparamos la animación de ordenamiento por si se produce algún cambio en la lista
    let reorderAnimationLaunched = false; 
    const reorderAnimation = getAnimation(managerNode, 'reorder');

    newList.forEach((newItem, newIndex) => {
        const idKey = newItem.idKey;
        const oldIndex = currentOrderIdKeys.indexOf(idKey);

        // Si oldIndex === newIndex, no hacemos nada. El nodo está en su sitio.
        if (oldIndex === -1) {
            // CASO: El nodo es nuevo. Lo creamos e insertamos.

            // MUTACION TEMPORAL DE PROPS
            component.props[listName] = [newList[newIndex]]; //modificamos los props del componente con solo el nodo que queremos crear

            const newVNode = managerNode.renderChildren()[0]; //renderizamos para crear el nuevo nodo

            newVNode.props.inList = managerNode.managerOf; //le indicamos al nuevo nodo a que lista pertenece (si es un componente, así podrá hacer setProp con el contexto correcto)

            const newDomNode = createDomNode(newVNode, managerNode); //creamos el nodo para el DOM con el nuevo nodo renderizado
            
            managerNode.dom.insertBefore(newDomNode, managerNode.children[newIndex]?.dom || null); //lo añadimos al DOM, en la posición pedida delante del nodo adecuado o al final si se sale de index

            managerNode.children.splice(newIndex, 0, newVNode); // lo añadimos al vTree

            // Lo añadimos a nuestro orden actual para sincronizar posiciones para las siguientes iteraciones y poder seguir ordenando correctamente
            currentOrderIdKeys.splice(newIndex, 0, idKey);

            //Lanzamos su MountAnimation
            const mountAnimation = getAnimation(newVNode, 'mount');
            applyAnimation(newVNode.dom, mountAnimation);

        } else if (oldIndex !== newIndex){
            //llegamos a este punto, sabemos que la lista se va a va a reorndear. 
            //Lanzamos la animación de actualización del contenedor (si la tiene definida, sino será un estandar)
            if(!reorderAnimationLaunched){             
                applyAnimation(managerNode.dom, reorderAnimation);
                reorderAnimationLaunched = true;
            }
            // CASO: El nodo ya existía y necesita moverse
            const vNodeToMove = managerNode.children[oldIndex]; //el nodo virtual que necesitamos mover
            const domNodeToMove = vNodeToMove.dom; //la referencia al DOM del nodo
            
            // Movemos en el DOM real
            managerNode.dom.insertBefore(domNodeToMove, managerNode.children[newIndex]?.dom || null);  

            // Movemos en el vTree 
            managerNode.children.splice(oldIndex, 1); //eliminamos el nodo virtual la antigua posicion
            managerNode.children.splice(newIndex, 0, vNodeToMove); //lo insertamos en la nueva

            // Actualizamos nuestro array de posiciones actuales para reflejar el cambio
            currentOrderIdKeys.splice(oldIndex, 1);
            currentOrderIdKeys.splice(newIndex, 0, idKey);
        }            
    });
}

function updateDynamicList(managerNode, oldList, newList, component, listName){
    //Hacemos un mapa de idKey con las propiedades de la lista con el estado sin actualizar para que sea más fácil comparar
    const oldListMapped = Object.fromEntries(
        oldList.map(item => [
            item.idKey, // key: idKey
            Object.fromEntries(
                Object.entries(item)
                    .filter(([k]) => k !== 'idKey')
                    //.filter(([k]) => k.startsWith('$')) // ya no filtramos solo dinámicas, se evaluaran todos los cambios que suceden
            )
        ])
    );

    // Un mapa de propiedades con el estado actualizado
    const newListMapped = Object.fromEntries(
        newList.map(item => [
            item.idKey, // key: idKey
            Object.fromEntries(
                Object.entries(item)
                    .filter(([k]) => k !== 'idKey')
                    //.filter(([k]) => k.startsWith('$'))
            )
        ])
    );

    // Recorremos la nueva lista para ver si hay algún nodo 
    newList.forEach((item, i) => {
        const idKey = newList[i].idKey;
        const newItem = newListMapped[idKey];
        const oldItem = oldListMapped[idKey];

        //si la key no existia en el objeto anterior, saltamos. Necesita ser creado no actualizado 
        if (oldItem === undefined) return; 

        const propsToUpdate = {}; //objeto en el que registraremos los cambios

        for (const prop in newItem) {
            //Si la propiedad ha cambiado, la añadimos a cambios a realizar
            if (newItem[prop] !== oldItem[prop]) {
                propsToUpdate[prop] = newItem[prop];
            }
        }

        for (const prop in oldItem) {
            //Si alguna propiedad del item antiguo no existe en el nuevo item, la eliminamos.
            if (!(prop in newItem)) {
                propsToUpdate[prop] = null;
            }
        }

        // si alguna propiedad ha cambiado, si es componente actualizamos sus props, si es un nodo lo re-renderizamos
        if (Object.keys(propsToUpdate).length > 0) { 
            //Iteramos en los hijos de managerNode hasta encontrar el nodo antiguo que necesitamos reemplazar

            let indexUpdatingChild; //guardamos el index porque nos permite sobreescribir el nodo virtual completo
            for (let i = 0; i < managerNode.children.length; i++) {
                if (managerNode.children[i].idKey === idKey) {
                    indexUpdatingChild = i;
                    break; 
                }
            }
            if (indexUpdatingChild == null){ 
                console.warn(
                `No se ha encontrado al hijo con idKey ${idKey} en la lista del vTree:`, newList, managerNode);
                return;
            }

            // Si es un componente, lo actualizamos con las props que hayan cambiado
            // El se encargara de gestionar su estado, no necesita re-renderizarse
            if (managerNode.children[indexUpdatingChild].isComponent){
                updateTree(managerNode.children[indexUpdatingChild], propsToUpdate);
            }

            //Los nodos no-componente se re-renderizan enteros modificando temporalmente el closure de su padre para realizar los cambios
            else{
                // Mutación temporal de las props del componente para que solo se re-renderice el nodo afectado
                component.props[listName] = [newList[i]]; 
                // Renderizamos los hijos, solo se generará uno: el que necesitamos con las props actualizadas
                const updatedVNode = managerNode.renderChildren()[0];

                updatedVNode.props.inList = managerNode.managerOf; //le indicamos al nuevo nodo a que lista pertenece (si es un componente, así podrá hacer setProp con el contexto correcto)

                const updatedNode = createDomNode(updatedVNode, managerNode); //creamos el nodo para el DOM con el nuevo nodo renderizado

                managerNode.children[indexUpdatingChild].dom.replaceWith(updatedNode); //lo reemplazamos en el DOM
                managerNode.children[indexUpdatingChild] = updatedVNode; //lo reemplazamos en el vTree

                //lanzamos la animación de actualización del elemento de la lista
                const updateAnimation = getAnimation(updatedVNode, 'update');
                applyAnimation(updatedNode, updateAnimation);
            }
        }
    });
}


// ANIMACIONES POR DEFECTO - se pueden modificar según las necesidades o dejarlas vacías si es necesario.
// En cada Componente/ManagerNode de cualquier lista tenemos la posibilidad de sobreescribir las animaciones
const defaultUpdateAnimation = {
    from: { 
        color: '#1a73e8',              // azul destacado (puedes cambiarlo a verde o el color de acento de tu app)
        filter: 'brightness(1.3)',     // un ligero brillo
        transition: 'all 0s'
    },
    to: { 
        color: 'inherit',              // vuelve al color que tenga por su clase
        filter: 'brightness(1)', 
        transition: 'all 0.6s ease'
    }
}
const defaultMountAnimation = {
    from: { 
        opacity: 0, 
        transform: 'scale(0.8) translateY(-5px)', 
        backgroundColor: 'lightblue', 
        transition: 'all 0s' 
    },
    to: { 
        opacity: 1, 
        transform: 'scale(1) translateY(0)', 
        backgroundColor: 'transparent', 
        transition: 'all 0.5s ease' 
    }
} 
const defaultReorderAnimation = {
    from: { opacity: 0, transition: 'all 0s' }, //importante: fijar el tiempo de transición a 0s en "from" para generar el cambio instantaneo
    to:   { opacity: 1, transition: 'all 0.5s ease'}
}
const defaultUnmountAnimation = {
    from: { opacity: 1, transform: 'scale(1)', transition: 'all 0.3s ease' },
    to:   { opacity: 0, transform: 'scale(0.95)', transition: 'all 0.3s ease' }
};

/**
 * Obtiene la definición de una animación para un VNode.
 * Busca la animación en las props del nodo (`props` o `domProps` para slots)
 * y devuelve una animación por defecto si no se encuentra ninguna personalizada.
 *
 * @param {object} vNode - El nodo virtual.
 * @param {string} animationType - El tipo de animación ('mount', 'unmount', 'update', 'reorder').
 * @returns {object} El objeto de definición de la animación.
 */
function getAnimation(vNode, animationType) {
    const animationPropName = `${animationType}Animation`; // ej: 'unmountAnimation'
    
    // Elige el objeto de propiedades correcto (props normales o domProps para slots)
    const props = (vNode.type === 'slot') ? vNode.domProps : vNode.props;

    // Busca la animación personalizada
    const customAnimation = props?.[animationPropName];
    if (customAnimation) {
        return customAnimation;
    }

    // Devuelve la animación por defecto según el tipo
    switch (animationType) {
        case 'mount':
            return defaultMountAnimation;
        case 'unmount':
            return defaultUnmountAnimation;
        case 'update':
            return defaultUpdateAnimation;
        case 'reorder':
            return defaultReorderAnimation;
        default:
            // Devuelve null o un objeto vacío si el tipo no es válido,
            // para que `applyAnimation` no haga nada.
            return null; 
    }
}

function applyAnimation(domNode, animation){        
    if(!animation) return;

    Object.assign(domNode.style, animation.from);

    //domNode.offsetWidth; //fuerza reflow. Funciona, pero lo "no-optimo" es que fuerza a hacer un render al instante

    //La solución parece más optima así. Le decimos al navegador "dentro de 2 frames, cuando estemos seguros de que se ha aplicado el "from" de la animación, lnazamos el siguiente.
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            Object.assign(domNode.style, animation.to);
        });
    });
}

// Esta funcion se encargará de manejar las listas, tanto en el DOM real como en el virtual
// También se encarga de dejar la propiedad del componente en el estado que estaba
//IMPORTANTE: esta función actualizará las propiedades del componente después de gestionar las listas
function handleDynamicList(rendererNode, managerNode, listName, oldList, newList){  
        //0- Guardamos la lista actual, tal y como está en el componente. Nos servirá para restaurarla de cualquier mutación temporal que pueda ocurrir durante la creación de nodos
        const updateRendererList = Array.isArray(rendererNode?.props?.[listName]) ? true : null;

        const updateManagerList = Array.isArray(managerNode?.props?.[listName]) ? true : null;

        //1- Buscamos el nodo manager, el padre de la lista dinámica desde el que manejaremos todos los cambios y actualizaciones
        //const managerNode = findManagerNode(rendererNode, listName);

        //2- Actualizamos los nodos que hayan cambiado sus valores internos
        updateDynamicList(managerNode, oldList, newList, rendererNode, listName); 

        //3- Eliminamos/creamos los nodos necesario y los ordenamos
        organizeDynamicList(managerNode, oldList, newList, rendererNode, listName);
        // NOTA -> si se produce algún cambio, hay que re-renderizar el elemento entero. El closure de sus propiedades está atrapado en el forEach y es innacesible

        //4- Actualizamos las propiedades de la lista
        if(updateRendererList) rendererNode.props[listName] = newList;
        if(updateManagerList) managerNode.props[listName] = newList; 
}

//Lanza la ejecución de los beforeUnMount y guarda la ejecución de los beforeUnmount de los componentes
function prepareSlotRemoval(slot) {
    const slotChildren = slot.children;
    const executeAfterUnmount=[]

    slotChildren.forEach( (child) => {  
        if (child.isSlot){
            executeAfterUnmount.push(...prepareSlotRemoval(child));
        }
        else if(child.isComponent){
            //ejecutamos el "beforeUnmount" al momento
            if (typeof child.props?.beforeUnmount === "function"){
                child.props.beforeUnmount(child);
            } 

            //guardamos el hook "afterUnmount" del componente
            if (typeof child.props?.afterUnmount === "function"){
                executeAfterUnmount.push(() => child.props.afterUnmount(child));
            }
        }
    });

    return executeAfterUnmount;
}

function handleSlot(slot, changeset){
    if (!changeset || Object.keys(changeset).length === 0) return; //si no hay cambios, salgo

    //Guardo la variable a ver si hay cambios en el slot
    const slotNewChildren = changeset[slot.slotName];

    //Si hay cambios en el estado de slot, los gestionamos
    if (slotNewChildren){   

        //Se crea un mapa para trabajar rápido con los nuevos elementos
        const newChildrenMap = new Map();
        slotNewChildren.forEach( (vnode,index) => {
            vnode.index = index;
            newChildrenMap.set(vnode.idKey, vnode);
        });

        //Se genera un array para guardar el orden real del DOM de una forma simplificada y poder ordenar fácilmente los nodos cuando llegue la fase de ordenación 
        const slotOldChildren = slot.props;

        const currentChildrenOrder = [];
        slotOldChildren.forEach(oldChild => {
            currentChildrenOrder.push(oldChild.idKey);
        });

        //FASE DE ELIMINACIÓN - se eliminan los nodos de las keys que ya no existan
        for (let i = slotOldChildren.length - 1; i >= 0; i--) {
            const oldChildKey = slotOldChildren[i].idKey;
            const newChild = newChildrenMap.get(oldChildKey);

            if(!newChild){
                const oldVNodeToDestroy = slot.children[i];

                //si es un slot, tenemos que gestionar los before/after UnMount de sus componentes/slots hijos
                let executeAfterUnmount = [];
                if(oldVNodeToDestroy.type === 'slot'){
                    executeAfterUnmount = prepareSlotRemoval(oldVNodeToDestroy); //aqui se ejecutan todos los unMount
                }
                destroyDOMNode(oldVNodeToDestroy); //eliminamos el slot entero, con todos los componentes y nodos de dentro con una sola acción
                executeAfterUnmount.forEach(execution => {
                    execution(); //ejecutamos los hooks afterUnMount
                })
                slot.children.splice(i, 1); //lo eliminamos de las propiedades
                currentChildrenOrder.splice(i, 1); //lo eliminamos de nuestro orden actual para mantenerlo actualizado

                //TO-DO: eliminar las dependencias globales de este componente eliminado si ningún otro hijo las necesita
            }
        }

        //mapa para poder acceder rapidamente a las keys de los viejos elementos
        const oldChildrenMap = new Map();
            slotOldChildren.forEach( (vnode,index) => {
            vnode.index = index;
            oldChildrenMap.set(vnode.idKey, vnode);
        });

        //FASE DE CREACIÓN DE NUEVOS NODOS O ACTUALIZACIÓN
        slotNewChildren?.forEach((newChild, newIndex) => {
            const oldChild = oldChildrenMap.get(newChild.idKey);  

            //1. Si el nodo no existía, tendremos que crear un nodo nuevo
            if(!oldChild){
                
                const newVNode = renderSlotChild(newChild, slot.slotContext);
                setSlotDependencies(slot.dependsOn, newVNode); //seteo en el slot las dependencias globales que tenga el nuevo hijo para pasarselas cuando haya actualizaciones

                const newDomNode = createDomNode(newVNode, slot);

                const siblingNode = slot.children[newIndex];
                //Si existe en nodo en esa posición, lo insertamos delante
                if (siblingNode){
                    siblingNode.dom.insertAdjacentElement('beforebegin', newDomNode);
                }
                //Si no existe ningún nodo en esa posición, lo insertamos al final
                else{
                    const lastNode = slot.children[slot.children.length - 1];
                    lastNode.dom.insertAdjacentElement('afterend', newDomNode);
                }

                slot.children.splice(newIndex, 0, newVNode); //lo añadimos al vTree
                currentChildrenOrder.splice(newIndex, 0, newChild.idKey); //lo añadimos a nuestra lista para mantener las posiciones actualizadas

                //si tiene función "onMount", la lanzamos
                if (typeof newVNode?.props?.onMount === "function"){
                    newVNode.props.onMount(newVNode);
                }

                //por último aplicamos la animación mount
                const mountAnimation = getAnimation(newVNode, 'mount');
                applyAnimation(newDomNode, mountAnimation);
                
                return;
            }
            // 2. Si no ha habido cambios en el nodo, o el nodo no es ni componente ni slot. hemos acabado.
            // NOTA: Los nodos que no son componentes son estáticos después de la creación, solo se modificarían las listas internas que tuvieran. Si queremos sustuir un nodo normal, modificamos la key para avisar de la nueva creación y eliminación del anterior.
            if(newChild === oldChild || (typeof oldChild.type !== 'function' && oldChild.type !== 'slot')) return;

            //3. Ha habido cambios
            //El nuevo nodo es un componente
            if(typeof newChild.type === 'function'){  
                // si es el mismo componente y su defMap no ha cambiado, solo necesitamos actualizarlo
                // TO-DO: se puede implementar un "pre-step" para que si cambia el defMap solo se tengan que actualizar sus propiedades. Pero normalmente un cambio de defMap implica utilizar el mismo componente de una forma distinta.
                if (newChild.type.name === oldChild.type.name && oldChild.props.defMap === newChild.props.defMap){
                    //filtramos para pasar solo las keys dinámicas 
                    const newDinamycProps = Object.fromEntries(
                        Object.entries(newChild.props).filter(([key]) => key.startsWith('$'))
                    );                             
                    updateTree(slot.children[oldChild.index], newDinamycProps); //actualizamos el componente con las nuevas props                               
                    return;                  
                }
                //si es un tipo distinto de componente, renderizamos el nuevo component
                else{
                    const newVComponent = renderSlotChild(newChild, slot.slotContext);
                    setSlotDependencies(slot.dependsOn, newVComponent); //seteo en el slot las dependencias del nuevo componente
                    const newDOMComponent = createDomNode(newVComponent, slot);
                    const VNodeToDestroy = slot.children[oldChild.index];

                    destroyDOMNode(VNodeToDestroy, newDOMComponent); //reemplazamos el nodo el DOM y se gestiona su unMount

                    slot.children[oldChild.index] = newVComponent; //lo sustituimos en el vTree

                    //si tiene función "onMount", la lanzamos
                    if (typeof newVComponent?.props?.onMount === "function"){
                        newVComponent.props.onMount(newVComponent);
                    }
                    //Lanzamos su MountAnimation
                    const mountAnimation = getAnimation(newVComponent, 'mount');
                    applyAnimation(newDOMComponent, mountAnimation);                    
                }
            }
            else if(newChild.type === 'slot'){
                const nestedSlot = slot.children[oldChild.index]; //apuntamos al nodo virtual
                const filteredChangeSet = {};
                if(newChild !== oldChild){           
                    //le pasamos su estado actualizado con el nombre de su slot como key
                    const nestedSlotName = nestedSlot.slotName;
                    
                    filteredChangeSet[nestedSlotName] = newChild.props.childrenDef; //buscamos el objeto que define su estructura en el estado
                }
                handleSlot(nestedSlot, filteredChangeSet); //con su estado actualizado ya preparado, se hace una llamada normal
            }            
        });

        
        //Preparamos la animación de ordenamiento por si se produce algún cambio en la lista
        let reorderAnimationLaunched = false; 
        const reorderAnimation = (slot?.domProps?.reorderAnimation) 
            ? slot.domProps.reorderAnimation
            : defaultReorderAnimation;

        //FASE DE ORDENACIÓN DE NODOS
        slotNewChildren.forEach((newChild, newIndex) => {
            const idKey = newChild.idKey;
            const oldIndex = currentChildrenOrder.indexOf(idKey);

            // Si oldIndex === newIndex, no hacemos nada. El nodo está en su sitio.

            if (oldIndex !== newIndex){
                //lanzamos la animación de reordenamiento
                if(!reorderAnimationLaunched){             
                    applyAnimation(slot.dom, reorderAnimation);
                    reorderAnimationLaunched = true;
                }
                // CASO: El nodo ya existía y necesita moverse
                const childToMove = slot.children[oldIndex]; //el nodo virtual que necesitamos mover
                const domChildToMove = childToMove.dom; //la referencia al DOM del nodo
                
                // Movemos en el DOM real
                slot.dom.insertBefore(domChildToMove, slot.children[newIndex]?.dom || null);  

                // Movemos en el vTree 
                slot.children.splice(oldIndex, 1); //eliminamos el nodo virtual la antigua posicion
                slot.children.splice(newIndex, 0, childToMove); //lo insertamos en la nueva

                // Actualizamos nuestro array de posiciones actuales para reflejar el cambio
                currentChildrenOrder.splice(oldIndex, 1);
                currentChildrenOrder.splice(newIndex, 0, idKey);
            } 
        });           
        
        slot.props = slotNewChildren; //actualizamos las props después de haber realizado todos los cambios
    }

    //Antes de acabar, miramos a ver si alguna lista dinámica global en el slot necesita actualización
    Object.keys(changeset).forEach(change => {
        //Si el slot depende de algún cambio, y este cambio no es el objeto de definición del slot, es una propiedad global
        if(slot.dependsOn[change] && change !== slot.slotName) {
            const foundManagerNodes = findAllManagerNodes(slot, change); //buscamos los nodos que manejan la lista

            if(foundManagerNodes.length !== 0) {
                foundManagerNodes.forEach(managerNode => {
                    handleDynamicList(managerNode, managerNode, change, managerNode.props[change], changeset[change]); //el nodo que maneja la lista dentro de un slot es simultaneamente renderizador y manejador
                    //managerNode.props[change] = changeset[change]; //actualizamos la lista del manager node
                });  
            }
        } 
    });
            
    //Miramos a ver si alguno de nuestros hijos necesita alguna propiedad global
    //Esta parte sirve para gestionar los cambios globales que afectan a las propiedades del slot
    //Si es un componente del slot el que ha producido el cambio global, este actualización será redundante porque las propiedades del slot ya se habrán actualizado en la fase anterior. "updateTree" no realizará ningún cambio
    slot.children.forEach( (child, index) => {
        //Si algún hijo slot lo necesita, le pasamos la propiedad
        if(child.isSlot) {
            const globalChanges = {};
            Object.keys(changeset).forEach(change => {
                if(child?.dependsOn?.[change]){ 
                    globalChanges[change] = changeset[change];
                }
            });
            handleSlot(child, globalChanges);
        }
        else if (child.isComponent) {
            const componentChanges = {};
            const slotPropsChanges = {}
            Object.keys(changeset).forEach(change => {
                const updateMapped = child.props?.updateMap?.[change];
                // Si un componente tiene el cambio en su "updateMap", es que es una propiedad global de la que depende.
                // Comprobamos a ver si el valor es diferente
                if(updateMapped && child.props?.[change] !== changeset[change]) {
                    componentChanges[change] = changeset[change]; //guardamos el cambio para aplicar en el DOM
                    slotPropsChanges[updateMapped] = changeset[change]; //guardamos el cambiar para modificar las props del slots
                } 
            });
            updateTree(child, componentChanges); //Actualizamos el componente con los cambios detectados

            // Actualizamos las propiedades del slot para que estén sincronizadas con las de su componente hijo
            Object.keys(slotPropsChanges).forEach(prop => {
                slot.props[index].props[prop] = slotPropsChanges[prop];
            });                
        }
    });      
}

/**
 * Inicia el proceso de actualización del árbol virtual para reflejar los cambios en el DOM.
 *
 * Esta función recorre el `virtualTree` de forma recursiva. No compara árboles completos,
 * sino que propaga un `changeset` (un objeto con solo los cambios de estado) a cada nodo.
 *
 * Cada `vNode` decide si necesita reaccionar a estos cambios basándose en sus dependencias.
 * Gracias a cómo están construidos los nodos, la propagación es eficiente:
 * solo los cambios relevantes para los hijos son pasados hacia abajo en el árbol.
 *
 * El resultado final son actualizaciones quirúrgicas en el DOM, realizadas solo cuando
 * y donde son estrictamente necesarias.
 *
 * @param {object} vNode - El nodo virtual (o la raíz del árbol) a procesar.
 * @param {object} changeset - Un objeto que contiene solo las claves del estado que han cambiado.
 */
function updateTree(vNode, changeset) {   
    // No hay nada que actualizar si:
    // a) Si el changeset no contiene ningún cambio
    // b) El node no es dinámico
    if (!Object.keys(changeset).length > 0 ||!vNode.isDynamic){
        return;
    }

    //Si el nodo es un slot, "handleSlot()" se encarga de gestionarle a él y a sus hijos
    if (vNode.type === 'slot') {
        handleSlot(vNode, changeset);
        return;
    }

    // 2- Si es dinámico, comprobamos iterando sobre las keys de "changeset" para ver que ha cambiado
    const previousProps = {}; //guardamos los valores anteriores e las props que vayan a cambiar. Los hooks lo utilizaran.
    let propsToUpdate = {}; //guardamos los valores a los que las props van cambiar
    const listsToHandle = {}; //guardamos las listas que vamos a manejar
    const changeSetKeys = Object.keys(changeset);

    for (let i = 0; i < changeSetKeys.length; i++) {
        const key = changeSetKeys[i];

    // si el elemento tiene una key, comparamos los valores
        if (vNode.props.hasOwnProperty(key)) {
            const oldValue = vNode.props[key];
            const newValue = changeset[key];

            if (oldValue === newValue) {
                continue;
            } else {
                //guardamos los valores
                previousProps[key] = oldValue; 
                propsToUpdate[key] = newValue;

                //Si la key hace referencia a alguna propiedad interna o externa, tenemos que manejarla
                let relatedKey;
                if(vNode.props?.updateMap?.hasOwnProperty(key)){
                    relatedKey = vNode.props.updateMap[key];

                    //guardamos los valores
                    previousProps[relatedKey] = oldValue; 
                    propsToUpdate[relatedKey] = newValue;
                }
                //Y al reves, si alguna key hace referencia a una propiedad externa, la pasamos. Así, cuando una propiedad interna está "unida" a una externa, siempre se actualizarán conjuntamente, se actualice la que se actualice (ya sea por el camino de "setProp", desde un ComponentController o que otro componente actualizó la propiedad global)
                else if(vNode.props?.defMap?.hasOwnProperty(key)){
                    relatedKey = vNode.props.defMap[key];

                    //guardamos los valores
                    previousProps[relatedKey] = oldValue; 
                    propsToUpdate[relatedKey] = newValue;
                }

                //Si es un componente, y la propiedad a actualizar es una lista, tendremos que manejarla
                if (vNode.isComponent) {
                    const dynamicListKey = vNode.dynamicLists.has(key) ? key
                        : vNode.dynamicLists.has(relatedKey) ? relatedKey: null;

                    if (dynamicListKey){
                        listsToHandle[dynamicListKey] = {oldList: oldValue, newList: newValue}; //añadimos la lista para ser manejada
                    }                        
                }
            }
        }
    }
    // COMPONENTES - LEY DE LA COMPUERTA
    // No hay keys para actualizar y el nodo es un componente, se corta la rama
    if (vNode.isComponent && Object.keys(propsToUpdate).length < 1){
        return;
    }
    
    // Hay propiedades para actualizar, las actualizamos
    let propChanges = {};  

    if(Object.keys(propsToUpdate).length > 0){
        
        //lanzamos el hook "beforeUpdate" del componente
        if (vNode.isComponent && typeof vNode.props?.beforeUpdate === "function"){
            // Nos aseguramos de que los cambios realizados por "setProp" en el nodo virtual del componente no se deshacen
            // NOTA: no es necesario, es contraproducente. los cambios que se realicen en el nodo virtual durante el "beforeUpdate" hook queremos que se mantengan precisamente para que no se vuelva a actualizar en el updateTree() que se lance en el siguiente loop provocado por las actualizaciones que sucedan dentro de "beforeUpdate"
            scheduler.stopUndoingChanges();

            // Llama al hook beforeUpdate y guardamos los cambios que nos pide aplicar en este mismo loop
            const updatesRequested = vNode.props.beforeUpdate(vNode, propsToUpdate, previousProps);

            if (typeof updatesRequested === 'object' && updatesRequested !== null) {
                // Las propiedades de `updatesRequested` sobreescribirán las de `propsToUpdate` si hay colisión.
                propsToUpdate = { ...propsToUpdate, ...updatesRequested };    
            }else{
                console.warn( `El componente ${componentName} no ha devuelto ningún cambio para realizar en su hook "beforeUpdate". Si se ha realizado algun setProp durante el hook, es posible que los datos se hayan corrompido.`)
            }
            // Volvemos a activar el deshacer cambios
            scheduler.startUndoingChanges();
        }

        // Modificamos el vTree y obtenemos las instrucciones de actualización para el DOM real
        propChanges = updateVNode(vNode, propsToUpdate);

        //lanzamos el hook "afterUpdate" del componente
        if (vNode.isComponent && typeof vNode.props?.afterUpdate === "function"){
            vNode.props.afterUpdate(vNode, propsToUpdate, previousProps);
        }
    }

    // Si es un componente, manejamos listas y pasamos las props a los nodos hijos
    // NOTA: un componente nunca se re-renderiza en el DOM, solo sus nodos hijos
    if (vNode.isComponent){
        //1- ordenamos las listas dinámicas que se hayan actualizado
        for (const [key, { oldList, newList }] of Object.entries(listsToHandle)) {
            const foundManagerNodes = findAllManagerNodes(vNode, key); //buscamos los nodos que manejan la lista

            if(foundManagerNodes.length === 0) {
                console.warn(`No se pudo al nodo manager de la lista "${key}" en el componente`,vNode);
            } 

            foundManagerNodes.forEach(managerNode => {
                handleDynamicList(vNode, managerNode, key, oldList, newList); //el componente será el renderizador y el managerNode será la referencia para organizar
            });   

        }
        //2- iteramos sobre sus hijos pasando las props actualizadas
        vNode.children.forEach(vNodeChild => updateTree(vNodeChild, propsToUpdate)); 

        //3- Lanzamos el hook "afterRender" porque el componente ya está actualizado en el DOM
        if (typeof vNode.props?.afterRender === "function"){
            vNode.props.afterRender(vNode, propsToUpdate, previousProps);
        }

        return;
    }

    // Si es un nodo hijo de componente, tenemos que tener en cuenta sus dependencias y actualizar el DOM
    // NOTA: las dependencias nos permiten actualizar propiedades internas de los nodos hijos que son dependientes de propiedades internas del componente (que a su vez, estas propiedades internas son dependientes del estado global)
    if(!vNode.isComponent){
        // Guardaremos las dependencias a intentar ejecutar
        const dependenciesToUpdate = {};  

        for (let i = 0; i < changeSetKeys.length; i++) {
            const key = changeSetKeys[i];
            // comprobamos si el elemento tiene dependencia de
            if (vNode.dependsOn.hasOwnProperty(key)) {
                // Si una dependencia se ha actualizado
                dependenciesToUpdate[key] = changeset[key];

                // Si no tiene la propiedad en propsToUpdate la añadimos para pasarla a los hijos
                // El nodo utiliza las dependencias para ejecutarlas, pero también cumple la función de CARTERO con los nodos hijos que lo necesiten. El motor de generación del DOM virtual y el compilador se encargan de esto
                if(!propsToUpdate.hasOwnProperty(key)){
                    propsToUpdate[key] = changeset[key];
                }
            }               
        }

        // "calculateDependantProps": recalcula aquellas propiedades del nodo que dependen de una propiedad actualizada del componente. Es inteligente:
        // a) Si el nodo está actuando como cartero y no tiene renders asociados a la propiedad actualizada, no hace nada
        // b) Si el nodo tiene renders asociados a la propiedad actualizada, recalcula las propiedades y devuelve los cambios que se van a hacer en el DOM real
        const dependenciesChanges = calculateDependantProps(vNode, dependenciesToUpdate); 

        // Justamos los cambios de propiedad con los de dependencias
        const allChanges = {...propChanges, ...dependenciesChanges};
        // NOTA: no es necesario actualizar las propiedades dependendientes. 
        // Son funciones que dependen de otras propiedades externas al Nodo y que se actualizan en el momento de ejecución con los valores de su closure (el closure debe ser proporcionado por las props del Componente)
        
        // Modificamos el DOM real con todos los cambios
        patchDomNode(vNode.dom, allChanges);
        
        // Actualizamos todos los hijos del nodo hijo, pasandoles las props que necesitan actualizarse
        vNode.children.forEach(vNodeChild => updateTree(vNodeChild, propsToUpdate)); 
        return;
    }
}



// Sirve para el "defMap" (cuando queremos definir un componente fuera de slot)
//Usaremos p() como azucar sintactico para construir los props de entrada para los componentes a través del mapeo de variables internas <=> externas

export function p(props, defMap){
    const mappedProps = {};

    // Recorremos el defMap y extraemos de props las claves externas
    for (const [internal, external] of Object.entries(defMap)) {
        if (!(external in props)) {
            throw new Error(
                `p(): la prop externa "${external}" definida para "${internal}" no existe en props.`
            );
        }
        mappedProps[external] = props[external];
    }

    // Añadimos el defMap al objeto final
    mappedProps.defMap = defMap;

    return mappedProps;
}

//Estas dos funciones servirás para poder acceder y setear el stado de un slot de forma idiomática. Podremos facilmente acceder al estado de los slots aninados.
    // $propRecover = getSlotState($slotAbuelo.$slotPadre.$slotHijo[2].props.$count;
    // $propToUpdate = getSlotState($slotAbuelo.$slotPadre.$slotHijo.$idKeyComponente.props;
    // Nota: ahora tambien se podrá buscar por idKey
    // $propToUpdate.$count = 11;


//DEPRECATED - ya no hace falta, con "get()" podemos obtener el objeto real a través del proxy
function getSlotState(slotPath){
    const path = Array.isArray(slotPath) ? slotPath : slotPath.split('.');
    if (!path.length) throw new Error(`Slot path erróneo: ${slotPath}`);

    let slotContext = state;

    slotContext = slotContext[path[0]]; //el primer nivel está a la vista como un objeto, simplemente accedemos

    //Si está más profundo, iteramos hasta situarnos sobre el array de objetos del slot
    for (let i = 1; i < path.length; i++) {
        const slotIndex = slotContext.findIndex(slotObj => slotObj.props.slotName === path[i]);
        if (slotIndex === -1) throw new Error(`Slot "${path[i]}" no encontrado en la ruta "${slotPath}"`);
        slotContext = slotContext[slotIndex]['props']['childrenDef'];            
    }

    if (!slotContext) throw new Error(`El slot "${slotPath}" no esta definido`);
    return slotContext;
}

function getSlotReactState(slotPath, pointParentSlot = false){
    if (stateReact === 'undefined') { 
        throw new Error(
            "Framework Error: No se encontró el estado reactivo. " +
            "Asegúrate de inicializar tu estado reactivo en una constante llamada 'stateReact'. " +
            "const stateReact = makeReactive(state)"
        );
    }

    const path = Array.isArray(slotPath) ? slotPath : slotPath.split('.');
    if (!path.length || !path[0]) throw new Error(`Slot path erróneo: ${slotPath}`);

    if(pointParentSlot){
        path.pop(); //si queremos apuntar al padre, eliminamos la última cadena de iteración. Esto le permite a la función externa modificar el slot pedido de golpe, no solo sus componentes internos uno a uno

        //Si no hay nada a lo que apuntar, es porque el padre es la raiz. La devolvemos.
        if(path.length === 0){
            return stateReact;
        }        
    }

    //let slotContext = state; //lo necesitamos como puntero para no hacer peticiones extras que puedan "ensuciar" la propertyChain del proxy

    //NOTA ARQUITECTURA: Después de la evolución del proxy, ya no es necesario tener un puntero. Cada "get" al proxy, se "limpia" automaticamente si no sea guarda. Ya no se se usa un solo "propertyChain" pasado por referencia. Ahora, al hacer la copia, el closure de cada nuevo proxy guardará el propertyPath hasta el lugar en el que estaba apuntando. 

    let slotContextReact = stateReact; //será el proxy apuntando al objeto pedido

    //el primer nivel está a la vista como un objeto, simplemente accedemos
    slotContextReact = slotContextReact[path[0]]; 

    //Si está más profundo, iteramos hasta situarnos sobre el array de objetos del slot
    for (let i = 1; i < path.length; i++) {
        const slotIndex = slotContextReact.findIndex(slotObj => slotObj.props.slotName === path[i]);
        if (slotIndex === -1) throw new Error(`Slot "${path[i]}" no encontrado en la ruta "${slotPath}"`); 
        slotContextReact = slotContextReact[slotIndex]['props']['childrenDef'];         
    }

    if (!slotContextReact) throw new Error(`El slot "${slotPath}" no esta definido`);
    return slotContextReact;
} 


//Funcion que se lanzará al inicializar un componente. Si es necesario, gestionará el defMap, y además devolverá la función setProp para cambiarle el valor a propiedades
export function initComponent(props, innerPropsKeys=[], componentName=''){       
    for (const neededProp of innerPropsKeys) {
        if(!(neededProp in props) && !(neededProp in props.defMap)){
            console.error(`Al componente ${componentName} no se le ha pasado la innerProp ${neededProp} ni directamente ni como global a través del defMap`)
            return;
        }
    }

    // verificar si alguna propiedad pasada no está en innerPropKeys
    const propKeys = Object.keys(props);    
    for (const propKey of propKeys) {
        // Verificamos si la clave de 'props' NO está incluida en 'innerPropsKeys'
        if (!['defMap','idKey','slotContext'].includes(propKey) && !innerPropsKeys.includes(propKey)) {
            console.warn(
                `El componente ${componentName} recibió la prop inesperada: ${propKey}. Esta prop no está listada en innerPropsKeys.`
            );
        }
    }
    

    // TO-DO trivial por consistencia : ahora mismo es el padre quien incrusta esta lista después de la definición de los hijos, entonces no es accesible en este momento, por eso es erroneo fijar este valor como constante aquí. Se podría modificar para hacer el trabajo de compilación para sacar el nombre de la lista desde el padre antes de compilar a los hijos e incrustar este dato en los hijos, tal y cómo se hace con los slots.
    
    // const myList = props.inList; // Guarda el nombre de la lista en la que se encuentra el componente (si se encuentra en alguna)

    const myIdKey = props.idKey; // Guarda el idKey del componente
    const mySetMap = props.defMap; // Guarda el mapa de las propiedades globales asignadas al componente

    // Esto funcionará para que funciones contenidas dentro del componente pueda modificar el estado global sin complejidades.
    const setProp = (propName, value=null) => {
        // Hacemos el cambio en las props del objeto para actualizar y poder trabajar con el closure actualizado antes de la siguiente renderización.
        const oldValue = props[propName]; //comparamos con el valor actual antes de hacer nada
        const bindedStateProp = props.defMap?.[propName];

        //Seteamos el cambio si
        if ( value !== oldValue   //Si el nuevo valor es distinto al valor guardado en el componente
            || (bindedStateProp && state[bindedStateProp] !== value) //o de la prop del estado asociada (si existiera)
        ){ 
            scheduler.registerChangeToUndone({ target: props, property: propName, value: oldValue }); //registramos el cambio para que se deshaga antes de hacer la actualización de ciclo para que el updateTree detecte el cambio correctamente
            props[propName] = value; //fijamos el valor para utilizarlo en el ciclo actual
        }
        else{ 
            console.warn(`El valor de la ${propName} del componente ${componentName} ya es ${value}, no es necesario actualizar el estado`);
            return;
        }

        //Se apunta al slot en el que está el componente
        //let slotContext = state;
        let slotReactContext = stateReact;

        //DONE: refactorizar para aprovechar el nuevo proxy: la nueva propertyChain, los nuevos "getByIdKey" (explicito mejor que backup) y el "get()" para solo necesitar el slotReactContext
        if(props.slotContext){
            //slotContext = getSlotState(props.slotContext);
            slotReactContext = getSlotReactState(props.slotContext);
        }

        
        // Llamada especial para auto-destruir el componente
        if(propName === 'selfdestroy'){
            if(props.inList){  
                //Si esta en una lista
                const index = slotReactContext[props.inList].findIndex(item => item.idKey === myIdKey);
                if (index !== -1) {
                    slotReactContext[props.inList].splice(index, 1);
                }
            }
            else if(props?.slotContext?.length > 0){
                //Si esta en un slot, 
                const index = slotReactContext.findIndex(item => item.idKey === myIdKey); //buscamo su index
                if (index !== -1) {
                    slotReactContext.splice(index, 1); //Y lo eliminamos del slot
                }
            }
            else{
                console.log(`El componente ${componentName} no puede autodestruirse porque está fuera de una lista o un slot`) 
            }
            return;
        }

        if (!propName.startsWith('$')) {
            console.log(`La propiedad ${propName} del componente ${componentName} no es dinámica y no se puede modificar`)
            return;
        }     
        
        //si el componente está en un slot
        if(props?.slotContext?.length > 0){
            //Si esta en un slot
            //const index = slotReactContext.findIndex(item => item.idKey === myIdKey);

            // Modifica su valor apuntando a la propiedad dentro de sus props
            // Si esta propiedad está relacionada con un propiedad global, el proxy se encargará de setearlo
            slotReactContext.getByIdKey(myIdKey)['props'][propName] = value;
        }
        else if(props.inList){  
            //Si esta en una lista
            //const index = slotReactContext[props.inList].findIndex(item => item.idKey === myIdKey);
            // Modifica el valor de su lista a través del índice
            slotReactContext[props.inList].getByIdKey(myIdKey)[propName] = value;
        }
        // Si el componente tiene un defMap apuntando a una propiedad global, la modificamos
        else if (mySetMap?.[propName]){
            const stateGlobalProp = mySetMap[propName]; 
            stateReact[stateGlobalProp] = value; //mandamos la actualización de la propiedad global
        }      
        else{
            console.error(`No se ha podido setear la propiedad ${propName} del componente ${componentName}. Asegurate de que está dentro de un slot o lista o que está correctamente asociada a una propiedad global.`)
        }

        //Ahora setProp devolverá este objeto, le servirá al hook "beforeUpdate" a añadir cambios en el mismo ciclo.
        const updatedProp = {};
        updatedProp[propName] = value;
        return updatedProp;
    }
    
    // Manejo de "targets"
    for (const key in props) {
        if (key.startsWith('target_') || key.startsWith('$target_')) {
            // Generamos los nombres de las nuevas funciones
            // ej: de "target_ControlledCounter" a "targetControlledCounter" y "targetControlledCounterProps"
            const name = key.substring(7); // "controlledCounter"
            
            // Usamos "target" como prefijo
            const targetVNodeFuncName = `target${name}`;      // "targetControlledCounter", "targetControlledSlot"
            const targetPropsFuncName = `target${name}Props`;  // "targetControlledCounterProps"
            const setSlot = `setSlot${name}`;  // "setSlotHeader"

            const targetInfo = props[key]; // { slotPath: '...', idKey: '...' }

            // --- Función 1: Devuelve el VNode completo ---
            props[targetVNodeFuncName] = () => {
                try {
                    // La búsqueda "just-in-time" se mantiene
                    const slotContext = getSlotReactState(targetInfo.slotPath);
                    if (targetInfo.idKey) {
                        return slotContext.getByIdKey(targetInfo.idKey);
                    }
                    return slotContext;
                } catch (e) {
                    console.warn(`No se pudo encontrar el VNode objetivo para ${key}`, e);
                    return null;
                }
            };

            //Si es un componente, creamos esta función para acceder a las props
            if(targetInfo.idKey){
                // --- Función 2: Devuelve solo las props (el atajo idiomático) ---
                props[targetPropsFuncName] = () => {
                    const targetVNode = props[targetVNodeFuncName](); // Reutilizamos la primera función
                    // Si el VNode existe, devuelve sus props; si no, devuelve null.
                    return targetVNode ? targetVNode.props : null;
                };
            }
            //Si no es un componente, es un slot. Daremos una función para modificarlo entero de golpe
            else{
                try {                        
                    props[setSlot] = (newChildren) => {
                        const parentContext = getSlotReactState(targetInfo.slotPath, true); //apuntamos al padre
                        const slotPath = Array.isArray(targetInfo.slotPath) ? targetInfo.slotPath : targetInfo.slotPath.split('.');
                        const finalTarget = slotPath[slotPath.length-1]; //apuntamos a nuestro slot                        
                        parentContext[finalTarget] = newChildren;
                        return true;
                    };
                } catch (e) {
                    console.warn(`No se pudo encontrar el slot objetivo para ${key}`, e);
                    return null;
                }
            }
            
            //Si el target no es dinámico, podríamos eliminar la propiedad. Lo dejamos por motivos de debug
            if (!key.startsWith('$')){
                //delete props[key]; 
            }

        }
    }

    if (!props.defMap) {
        console.log(`El componente ${componentName} no tiene defMap. No podrá actualizar las propiedades globales de "state", y si está fuera de una lista dinámica o slots, no podrá actualizarse.`) 
    }
    //el componente tiene defMap
    else {        
        // Comprobar que defMap no tiene claves extra
        const extraKeys = Object.keys(props.defMap).filter(key => !innerPropsKeys.includes(key));
        if (extraKeys.length > 0) {
            throw new Error(
                `initComponent: defMap contiene claves no declaradas en innerPropsKeys: ${extraKeys.join(', ')}. ` +
                `El defMap solo debe declarar las props que usa el componente.`
            );
        }
        /* 
        DONE: ampliar las posibilidades de creación de componentes para no necesitar usar la función "p" por obligación
        
        DEPRECATED - ahora se ofrece más flexibilidad, se pueden fijar propiedades estáticas directamente en la definición del componente, y luego hacer un defMap 
        
        if (!props.slotContext){
            // Comprobar que defMap cubre todas las innerPropsKeys
            // Nota: si el componente está en un slot, no es necesario que se le pasen todas las propiedades globales, sí es un componente suelto, sí.
            for (const key of innerPropsKeys) {
                if (!(key in props.defMap)) {
                    console.log(
                        `initComponent: falta la key "${key}" en defMap el defmap de ${componentName}. ` +
                        `El componente declaró que la necesita en innerPropsKeys pero no fue mapeada.`
                    );
                }
            }
        }*/
        // Creamos las propiedades internas del componente y le asignamos los valores externos que nos hayan pasado
        for (const [internal, external] of Object.entries(props.defMap)) {
            // Para un componente fuera de un slot
            if (!(external in state)){
                console.error(`La prop global ${external} pasada al componente ${componentName} no existe, no se modificará correctamente.`)
                return;
            }
            if (!props.slotContext){
                /*if (!(external in props)) {
                    throw new Error(
                        `initComponent: la prop externa "${external}" definida para ` +
                        `la clave interna "${internal}" no existe en props.`
                    );
                }*/

                // Un componente dentro de un slot siempre copiará la propiedad global, ya sea a través de las props pasadas o directamente del estado global.

                //Si se ha usado la función "p" para pasarnos los datos y tenemos un "p" externa, la fijamos directamente
                if(props[external]){
                    props[internal] = props[external];
                }
                else{
                    //Sino, directamente accedemos al estado definido en el defMap y lo fijamos tanto en las propiedades internas como las externas
                    let globalValue = state[external] 
                    props[external] = globalValue; 
                    props[internal] = globalValue;                    
                }

                // Si la prop externa no es dinámica (no empieza por $), 
                // y es diferente a la interna (a veces un desarrollador puede utilizar el mismo nombre para la propiedad global que la definida en el componente) 
                // No nos hace falta la referencia porque no se actualizará más y ya hemos guardado el valor inicial que necesitabamos.
                if (!external.startsWith('$') && external !== internal) {
                    delete props[external];
                }
            }
            else{
                //Dentro de un slot, es diferente. Si el componente ha fijado una propiedad interna, esta propiedad interna fijará el valor global en el momento de la creación. Esto nos permitirá crear componentes desde un controller que en el momento de su creación fijen la propiedad global

                let value = props[internal]; //asumimos que se ha fijado un valor en la proiedad interna
                if (!value){ //Si no se ha fijado un valor a la propiedad interna, fijamos el valor externo para nuestra propiedad interna y externa
                    let globalValue = state[external];
                    props[internal] = globalValue; 
                    props[external] = globalValue; //También lo fijamos como clave externa para recibir las actualizaciones

                }else{//Si SÍ se ha fijado un valor a la propiedad interna, mandamos una actualización de valor a la propiedad global del estado para que asuma este valor como el global. Esto nos permitirá fija el valor global en el paso de crear el objeto, lo que hace que los puedan fijar el estado global al crear un nuevo componente.
                    props[external] = props[internal];
                    stateReact[external] = props[internal];
                }
            }
        }

        // Creamos el mapa de actualización de propiedades internas para que updateTree() pueda usarlo directamente sin necesidad de recrearlo en cada iteración y facilitar el debug. Esto relaciona la propiedad del estado global con la propiedad interna que conocen los nodos de los componentes

        // $propExterna : '$propInterna', ejemplo: $counter10 : '$counter' (así podemos reutilizar el componente Counter las veces que necesitemos y compartir propiedades entre componentes)
        const updateMap = Object.fromEntries(
            Object.entries(props.defMap)
            .filter(([_, value]) => value.startsWith('$')) //solo guardaremos las clave dinámicas
            .map(([key, value]) => [value, key])
        );
        props.updateMap = updateMap;
    }

    //Si el componente tiene el hook "beforeUnmount" (recibe las props y el "setProp" para poder formatear las props recibidas antes de su montaje en el vTree)
    if(props.beforeInitComponent){
        //TO-DO: quizás exponer "setProp" en todos los casos para ser accedido por los target? Implicaría un cambio de lógica solo para las setProps (no permitiría al controller cambiar el tipo), pero centralizaría al modificar solo props. Aún así, habría que mantener los controles en el proxy para los casos de cambiar tipos.

        props.setProp = setProp; //para funciones que usen el beforeInitComponent, podremos usar setProp desde la propiedades para poder utilizar otras funciones

        //Durante la ejecución del hook, no registramos los undoneChanges porque ya se van a establecer directamente en este componente y sus hijos durante la definición.
        scheduler.stopUndoingChanges();
        props.beforeInitComponent(setProp);
        scheduler.startUndoingChanges();
    }

    return setProp;
}
/**
 * Crea un proxy recursivo que intercepta operaciones de lectura y escritura
 * sobre cualquier nivel de profundidad de un objeto o array.
 *
 * @param {Object|Array} target - El objeto o array que será envuelto por el proxy.
 * @param {Function} callback - Función que se ejecutará cada vez que se produzca una modificación (set).
 * @param {Object|Array|null} [stateRoot=null] - Referencia al objeto raíz stateRoot antes de aplicar proxies.
 * @param {Array<string>} [propertyChain=[]] - Lista de propiedades que representan la ruta completa
 *                                             hasta el valor actual. Permite reconstruir la ubicación exacta
 *                                             de cualquier cambio dentro del árbol de datos.
 *
 * @description
 * - `stateRoot` mantiene una referencia al state base sobre el que se aplican los proxies.
 * - `propertyChain` actúa como una traza de contexto: registra la cadena de propiedades accedidas,
 *   lo que permite detectar la posición exacta de un cambio y reconstruir estructuras de forma controlada.
 * - Combinando ambas (`stateRoot` + `propertyChain`), se obtiene un control total sobre el acceso y
 *   manipulación de datos anidados.
 */

function makeReactive(target, stateRoot=null, propertyChain=[]) {
    const handler = {
        // La trampa GET es clave: la interceptamos para devolver un proxy si la propiedad es un objeto/array
        get(target, property, receiver) {
            try{
                //Se utiliza para poder obtener el objeto real o la propiedad a través del proxy real.
                //reactState.$listFather.$list1.get()
                if (property === 'get') {
                    return () => target; // devuelvo una función que devuelve el target
                }
                //TO-DO: este mismo sistema es totalmente extensible a push, pop, shift y unshift
                if (property === 'splice') {                   
                    //Si se hace splice, creamos el nuevo array que se haya generado, y hacemos que nuestro set del proxy gestione el cambio             
                    return function(start, deleteCount, ...items) {
                        const newArray = [...target]; //creamos una copia del array

                        newArray.splice(start, deleteCount, ...items); //le aplicamos los cambios de splice

                        handler.set(target, property, newArray, receiver); //le pasamos el nuevo array listo al set para que lo gestione, se actualice el estado y se renderice el DOM
                    }
                }
                //Esto nos sirve para forzar la búsqueda por idKey
                if (property === 'getByIdKey') {
                    // devolvemos una función que recibe un idKey
                    return (idKey) => {
                        if(Array.isArray(target)){
                        const index = target.findIndex(item => item.idKey === idKey);  
                            if(index !== -1){
                                let value = Reflect.get(target, index, receiver); //fijamos el nuevo valor
                                const currentChain = [...propertyChain, index];
                                return makeReactive(value, stateRoot, currentChain);
                            }
                        }
                        return undefined;   
                    };
                }

                let value = Reflect.get(target, property, receiver);
                

                //Busqueda idKey Backup: Si no existe la propiedad pasada, intentamos buscar el objeto por "idKey" entre las distintas posiciones por si se ha pasado un idKey en vez de una posición
                if(!value){
                    if(Array.isArray(target)){
                        const index = target.findIndex(item => item.idKey === property);  
                        if(index !== -1){
                            value = Reflect.get(target, index, receiver); //fijamos el nuevo valor
                            property = index; //y fijamos la property como el index para devolver la propertyChain correcta
                        }                           
                    }                                     
                }

                // Si el valor es un objeto (y no nulo), lo envolvemos en un proxy
                if (typeof value === 'object' && value !== null) {

                    stateRoot = (stateRoot === null) ? target : stateRoot; //si stateRoot es null, es porque estamos apuntando al objeto state, es la primera llamada "get" de intento de "set" a otras capas. Quedará guardado en el closure para futuras llamadas.

                    //propertyChain.push(property); //añadimos la propiedad en la que se está haciendo para poder acceder a la cadena de propiedades para detectar 

                    const currentChain = [...propertyChain, property]; //creamos una copia para no ensuciar la propertyChain general
                    
                    return makeReactive(value, stateRoot, currentChain); // devolvemos un nuevo proxy sobre el objeto al que se ha accedido, y le pasamos la cadena de llamadas hasta su nivel y el stateRoot
                }
                return value;
            }
            catch (error){     
                console.error('Se ha intentado acceder a una propiedad que no existe', error);               
                console.error('propertyChain accedida = ', propertyChain);                    
                //propertyChain.splice(0, propertyChain.length);
            }
        },

        // La trampa SET, cuando intentemos modificar una propiedad
        set(target, property, value, receiver) {
            try{                    
                //si estamos intentando setear una propiedad del primer nivel, la modificamos directamente con el valor recibido, porque el proxy lo gestiona bien y aquí las referencias de "state" y "App" (que recibe ...state) son diferentes,
                if(propertyChain.length === 0){
                    Reflect.set(target, property, value, receiver); 
                }   

                //si estamos accediendo a una propiedad anidada, gestionamos la modificación para que salte el trigger al comparar los objetos, modificando solo lo necesario en cada capa
                else{  
                    
                    // CASO - un componente en un slot fija una propiedad global
                    // Si el target está dentro de "props", significa que estamos intentando fijar una propiedad de un slot. (es el único caso en el que "props" se utiliza dentro del state, para guardar las propiedades de un componente en un slot)
                    // Si esta propiedad existe en su defMap, significa que este componente necesita setear una propiedad global también
                    if(propertyChain?.[propertyChain.length-1] === 'props' && target?.defMap?.[property]){                        
                        const globalProp = target.defMap[property]; //apuntamos a la propiedad global que tenemos que modificar
                        stateRoot[globalProp] = value; //la propiedad global siempre está en el primer nivel, la modificamos
                    } 

                    let isArrayHandle = 0;

                    //TO-DO: solo splice está hecho
                    if (['splice', 'push', 'pop', 'shift', 'unshift'].includes(property)){
                        isArrayHandle = 1;
                        property = propertyChain[propertyChain.length-1]; //la propiedad que queremos modificar es la última propiedad de la cadena
                    }

                    //Busqueda idKey Backup: Si no existe la propiedad pasada, intentamos buscar el objeto por "idKey" entre las distintas posiciones por si se ha pasado un idKey en vez de una posición
                    else if(!target[property]){
                        if(Array.isArray(target)){
                            const index = target.findIndex(item => item.idKey === property);  
                            if(index !== -1) property = index; //fijamos el nuevo valor como index real                         
                        }
                    }//Si no se cumple, asumimos que se está intentando fijar una nueva propiedad
                    
                    // Si se modificando una lista situada en el primer nivel, asignamos el valor recibido del get directamente
                    if(propertyChain.length === 1 && isArrayHandle){
                        stateRoot[propertyChain[0]] = value;
                    }

                    // Si es una modificación más profunda, de arrays o propiedad, creamos un clon y lo modificamos selectivamente capa por capa para que salten los triggers de comparación
                    else{                        
                        // clonamos el primer nivel del objeto/array
                        const clone = Array.isArray(stateRoot[propertyChain[0]]) ? [...stateRoot[propertyChain[0]]] : { ...stateRoot[propertyChain[0]] };

                        let targetOriginal = stateRoot[propertyChain[0]]; // puntero al objeto original
                        let targetClone = clone; // puntero al clon en construcción

                        //Si estamos manejando un array, la última key de la cadena es nuestra propiedad, quedándonos un paso más atrás seremos capaces de hacer una sustitución completa de esa cadena para que se comporte correctamente
                        for (let i = 1; i < (propertyChain.length-isArrayHandle); i++) {
                            const key = propertyChain[i];                       
                            targetOriginal = targetOriginal[key];  //avanzamos en el original

                            // clonamos este nivel dentro del clon
                            targetClone[key] = Array.isArray(targetOriginal) ? [...targetOriginal] : { ...targetOriginal };
                        
                            targetClone = targetClone[key]; //movemos el puntero del clon a ese nivel
                        }                        
                        // Modificamos el valor de la propiedad o array
                        targetClone[property] = value;

                        // Incrustamos nuestro objeto clonado en el estado stateRoot en el primer nivel para comunicar el cambio correctamente
                        stateRoot[propertyChain[0]] = clone;

                        /* NUEVO: para manejar el caso de la incorrecta actualización de los props durante el mismo ciclo de renderizado
                        // estado inicial - target.props.$count: 11
                        target.props.$count = target.props.$count + 1; //target.props.$count = 12
                        target.props.$data = `cambio total de props ${target.props.$count}` // = `cambio total de props 11`

                        Introducimos un cambio temporal en las props del vTree para que mientras se ejecuta el ciclo actual se pueda acceder al estado ya cambiado. Será "scheluder" el encargado de devolver el arbol a su estado inicial antes de llamar de updateTree.

                        NOTA: en el setProp también se ha introducido este cambio. Pero este sigue siendo crucial para gestionar los cambios realizados desde los ComponentControllers. Al usar "target", no se pasa por "setProps".
                        */

                        // 1- Obtenemos el valor actual
                        const oldValue = Reflect.get(target, property, receiver); 
                        // 2- Registramos el cambio que vamos a hacer en el scheduler para que deshaga el cambio antes de actualizar el DOM
                        if (oldValue !== value) scheduler.registerChangeToUndone({ target, property, value: oldValue });
                        
                        //    changesToUndone.push({ target, property, value: oldValue });
                        // 3- Actualizamos el cambio en el target real (que puede ser state o las props guardadas en App, ya que se hace App(...state), lo que no crea un clon total, solo en el primer nivel
                        Reflect.set(target, property, value, receiver); //seteamos en el target real temporalmente
                    }
                }
                
                //NOTA ARQUITECTURA: Después de la evolución del proxy, ya no es necesario tener un puntero. Cada nuevo "set" al proxy, no necesita ser limpiado para proximos gets. Ahora cada proxy, al pasar propertyChain como copia en vez de como referencia, guardará su propio closure apuntando. Esto nos permite guardar punteros reactivos a diferentes slots o objetos para manejarlos con comodidad.
                // propertyChain.splice(0, propertyChain.length); //limpiamos la propertyChain para que empiece desde 0 en el siguiente acceso //Ya no hace falta
                
                scheduler.scheduleUpdate(); // El scheduler más tarde (en la fase de promesas) re-construirá el estado original del arbol y hará updateTree para renderizar los cambios
        
                return true;
            }
            catch (error){
                console.error('Se ha intentado setear a una propiedad que no existe', error);
                console.error('propertyChain accedida = ', propertyChain); 
                //propertyChain.splice(0, propertyChain.length);                    
            }
        }
    };

    return new Proxy(target, handler);
}

/**
 * Planificador de actualizaciones (Scheduler).
 * Se encarga de agrupar múltiples cambios de estado síncronos (batching)
 * en una única operación de renderizado para optimizar el rendimiento.
 */
const scheduler = {
    /** 
     * @type {boolean} 
     * Un flag que nos indica si ya hay una actualización encolada en la microtarea.
     * Esto previene que se encolen múltiples renderizados en el mismo tick.
     */
    isUpdateScheduled: false,

    undoingChanges: true,

    changesToUndone: [], //guardaremos los cambios reales realizados al vTree para el vTree pueda volver ponerse al estado stateRoot antes de que el scheduler ejecute el "updateTree"

    /**
     * Registra un cambio para ser deshecho antes del ciclo de actualización.
     * @param {object} changeToUndone - El objeto que describe el estado del nodo antes de la mutación.
     */
    registerChangeToUndone(changeToUndone) {
        if(this.undoingChanges){
            this.changesToUndone.push(changeToUndone);
        }
    },

    /**
    * Para de registrar cambios para deshacer
    */
    stopUndoingChanges() {
        this.undoingChanges = false;
    },

    /**
    * Empieza a registrar cambios para deshacer
    */
    startUndoingChanges() {
        this.undoingChanges = true;
    },


    /**
     * El método público que será llamado por el proxy cada vez que el estado cambie.
     */
    scheduleUpdate() {
        // Si ya hemos planificado una actualización para este ciclo, no hacemos nada más.
        // Este es el corazón del "batching".
        if (this.isUpdateScheduled) {
            return;
        }

        // Si no hay una actualización planificada, marcamos el flag inmediatamente.
        // Esto asegura que cualquier llamada síncrona posterior a esta función
        // durante el mismo tick sea ignorada.
        this.isUpdateScheduled = true;

        const self = this; //creamos la variable "self" para que la promesa pueda acceder correctamente al closure de nuestro objeto

        // Usamos `Promise.resolve().then()` para encolar la ejecución del renderizado
        // en la cola de microtareas. Esto garantiza que se ejecutará después de que
        // todo el código síncrono actual (ej: el manejador de un click) haya terminado.
        Promise.resolve().then(() => {
            try {
                // Rehacemos los cambios hechos en el virtual DOM, del último hecho al primero, para recuperar el estado original antes de recorrer el arbol con el nuevo estado
                // recorremos la lista al revés (LIFO)
                while (self.changesToUndone.length) {
                    const change = self.changesToUndone.pop(); //sacamos el último cambio y lo asignamos
                    try {
                        const { target, property, value } = change; 
                        target[property] = value;
                    } catch (err) {
                        console.error("No se pudo aplicar el cambio:", change, err);
                    }
                }

                self.isUpdateScheduled = false; //dejamos la flag abierta por si alguna ejecución del updateTree quiere setear una nueva actualización
                updateTree(virtualTree, state);
            } catch (error) {
                // Capturamos errores de renderizado
                console.error("Error en renderizado:", error);
                self.isUpdateScheduled = false; //nos aseguramos de dejar el flag abierta
                self.changesToUndone.length = 0; //y de que los cambios queden vacios
            }
        });
    }
};

//Contendrá el estado reactivo de la aplicación. Se iniciará con mount
let stateReact = null;
let virtualTree = null;
let mountQueue = [];
let state = null;
let debugOn = false;

export function plant(AppComponent, initState, container, debug=false) {
    debugOn = debug; //fijamos el debug
    scheduler.startUndoingChanges();
    state = initState; //inicializamos el estado y lo hacemos reactivo
    stateReact = makeReactive(state);    

    virtualTree = h(AppComponent, {...initState} ); //creamos el vTree con la definición

    //Si estamos en debug, exponemos "state" y "virtualTree" para ver el estado actual en cualquier punto del flujo
    if(debugOn){
        window.debug = {
            state,
            tree: virtualTree,
        }
    }

    // Limpiamos la cola por si es un "re-montaje"
    mountQueue = []; 
    
    // Fase 1: Crear todo el árbol DOM y llenar la cola.
    const rootDomNode = createDomNode(virtualTree); //se añadirán las funciones "onMount" a la "mountQueue" para ejecutarlas todas después de montado el arbol virtual
    
    // Fase 2: Añadir al DOM real (hacerlo visible).
    container.innerHTML = ''; // Limpiamos el contenedor
    container.appendChild(rootDomNode);

    // Fase 3: Ejecutar todos los callbacks de montaje.
    // Ahora cada callback recibe el vNode correcto que le corresponde.
    mountQueue.forEach(callback => callback());
    
    // Limpiamos la cola para la próxima vez.
    mountQueue = [];
}

//Componente vacio para ocupar espacio en una "idKey" de un slot o para usar en un Component Controller de solo lógica
export function Empty() {
    return h('span', { class: 'empty-placeholder', hidden: true });
}

/**
 * Transforma un objeto o un array de objetos añadiendo una nueva propiedad 'idKey'.
 * El valor de 'idKey' se toma de una propiedad existente del objeto, especificada por keyName.
 * La nueva propiedad se añade al principio de cada objeto gracias al spread syntax.
 *
 * @param {object|object[]} data - El objeto o array de objetos a transformar.
 * @param {string} keyName - El nombre de la propiedad de la cual se tomará el valor para 'idKey'.
 * @returns {object|object[]|any} El objeto o array transformado, o la entrada original si no es un objeto/array válido.
 */
export function addIdKey(data, keyName) {
    // Guarda de seguridad: si la entrada no es un objeto (o es null), no se puede procesar.
    if (!data || typeof data !== 'object') {
        return data;
    }

    // Caso 1: La entrada es un array. Se usa map para transformarlo.
    if (Array.isArray(data)) {
        return data.map(item => ({
            idKey: item[keyName],
            ...item
        }));
    }

    // Caso 2: La entrada es un único objeto.
    return {
        idKey: data[keyName],
        ...data
    };
}

/*Función que setea todas las props recibidas por un componente como global props. 
IMPORTANTE: elimina todo el defMap anterior que se el haya pasado al componente. El objetivo de esta función es para usarlo en la App de primer nivel que recibe el estado directamente o para casos de componentes muy especificos.
*/
export function setAllPropsAsDefMap(props) {
    props.defMap = {};
    
    const innerPropKeys = [];

    for (const key in props){
        if(key !== 'defMap'){
            props.defMap[key] = key;
            innerPropKeys.push(key);
        }
    }

    return innerPropKeys;
}

//TO-DO: implementar "unitTestComponent", una función que con pasarle un componente y su estado, monte el entorno del framework por si sola y espere a la actualización para hacer asserts del componente en función de su estado. Sería algo así:
    // const { container } = unitTestComponent(Component, { $count: 5 });
// Nota: no hace falta refactorizar nada importante, solo habría que encontrar la forma de hacer que esperara a la actualización del DOM para hacer el assert.

// En Component.test.js
/*
    import Component from './Component.js';
    import { unitTestComponent } from './test-utils.js';

    async test('el contador se incrementa al hacer clic', () => {
        // 1. Montaje
        const { container } = unitTestComponent(Component, { $count: 5 });

        // 2. Encontrar elementos y simular eventos
        const button = container.querySelector('button');
        const paragraph = container.querySelector('p');

        expect(paragraph.textContent).toContain('counter 5'); //"expect" tendría que esperar la actualización. Quizás la forma más fácil es hacer que la comprobación se encole como macrotarea para esperar a la actualización del ciclo anterior antes de comprobar. 
        // NOTA: esto solo funcionaría si no hay "fetchs" intermedios. Necesitaríamos mockear manualmente, utilizar una librería o implmentar una funcionalidad avanzada para poder mockear estos fetchs desde fuera y volver sus respuestas síncronas para que esto pudiera funcionar.

        button.click(); // El usuario hace clic

        // 3. Hacer la aserción
        // expect(paragraph.textContent).toContain('counter 6');
    });

*/

//TO-DO: routing. Escuchar eventos en la history API para actualizar la URL sin recargar la página, para tener una arquitectura que permita definir el estado inicial en función de la URL visitada. El renderizado de componentes vendrá definido en los slots, y el routing podría ser simplmente un componente más "Router" que apunte a los slots sobre los que aplicaremos modificaciones.

//TO-DO: mejorar las innerProps. En vez de un array de strings, sería mejor un objeto que pudiera setear valores por defecto. Luego se recuperarían las keys y todo funcionaría igual, pero sería mucho más explícito y variabo, incluso pudiendo migrar a forzar la entrada de determinados datos. (migrando a typescript por ejemplo, o implementandolo internamente)
