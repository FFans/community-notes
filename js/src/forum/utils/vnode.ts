import Mithril from 'mithril';

export function findVnodeByClass(node: Mithril.Children, className: string): Mithril.Vnode<any, any> | null {
  if (Array.isArray(node)) {
    for (const child of node) {
      const result = findVnodeByClass(child, className);

      if (result) {
        return result;
      }
    }

    return null;
  }

  if (!node || typeof node !== 'object' || !('attrs' in node)) {
    return null;
  }

  const vnode = node as Mithril.Vnode<any, any>;
  const classes = vnode.attrs?.className;

  if (typeof classes === 'string' && classes.split(/\s+/).includes(className)) {
    return vnode;
  }

  return findVnodeByClass(vnode.children, className);
}
